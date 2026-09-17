<?php

namespace Heyday\MenuManager\Test;

use Akqa\SilverStripe\TreeField\Form\TreeField;
use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers limiting how deep a single menu can go through MenuSet.max_menu_depth.
 */
class MenuMaxDepthTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInWithPermission(['MANAGE_MENU_SETS', 'MANAGE_MENU_ITEMS']);

        Config::modify()->set(MenuItemTreeSource::class, 'max_depth', 3);
    }

    private function limit(array $depths): void
    {
        Config::modify()->set(MenuSet::class, 'max_menu_depth', $depths);
    }

    private function source(string $setFixture = 'header'): MenuItemTreeSource
    {
        return MenuItemTreeSource::create()
            ->setScopeID($this->objFromFixture(MenuSet::class, $setFixture)->ID);
    }

    private function item(MenuItemTreeSource $source, string $fixture): MenuItem
    {
        return $source->getNode((string) $this->objFromFixture(MenuItem::class, $fixture)->ID);
    }

    private function nest(string $childFixture, string $parentFixture): void
    {
        $child = $this->objFromFixture(MenuItem::class, $childFixture);
        $child->ParentItemID = $this->objFromFixture(MenuItem::class, $parentFixture)->ID;
        $child->write();
    }

    public function testAMenuWithoutALimitUsesTheTreeDefault(): void
    {
        $this->limit(['Footer1' => 2]);

        $this->assertNull($this->objFromFixture(MenuSet::class, 'header')->getMaxMenuDepth());
        $this->assertSame(3, $this->source('header')->getMaxDepth());
    }

    public function testAMenuCanSetItsOwnLimit(): void
    {
        $this->limit(['Header' => 2]);

        $this->assertSame(2, $this->objFromFixture(MenuSet::class, 'header')->getMaxMenuDepth());
        $this->assertSame(2, $this->source('header')->getMaxDepth());
        $this->assertSame(3, $this->source('footer')->getMaxDepth(), 'Other menus are unaffected');
    }

    public function testTheLimitIsMatchedOnTheNormalisedName(): void
    {
        // The fixture is named "Footer 1", which is saved as Footer1
        $this->limit(['Footer1' => 1]);

        $this->assertSame(1, $this->source('footer')->getMaxDepth());
    }

    public function testTheLimitCanBeRaisedAboveTheTreeDefault(): void
    {
        $this->limit(['Header' => 4]);

        $this->nest('header-2', 'header-1');
        $this->nest('header-3', 'header-2');

        $source = $this->source();

        $this->assertTrue($source->canAddChildren($this->item($source, 'header-3')));
        $this->assertSame(4, $source->createNode($this->item($source, 'header-3'))->getMenuLevel());
    }

    public function testASecondLevelLinkCannotTakeChildrenInATwoLevelMenu(): void
    {
        $this->limit(['Header' => 2]);
        $this->nest('header-2', 'header-1');

        $source = $this->source();
        $top = $this->item($source, 'header-1');
        $second = $this->item($source, 'header-2');

        $this->assertTrue($source->canAddChildren(null));
        $this->assertTrue($source->canAddChildren($top));
        $this->assertFalse($source->canAddChildren($second));

        // The CMS hides the + on a row from this flag
        $this->assertTrue($source->getNodeData($top)['canAddChildren']);
        $this->assertFalse($source->getNodeData($second)['canAddChildren']);
    }

    public function testAddingBelowTheLimitIsRefused(): void
    {
        $this->limit(['Header' => 2]);
        $this->nest('header-2', 'header-1');

        $source = $this->source();

        $this->expectException(LogicException::class);

        $source->createNode($this->item($source, 'header-2'));
    }

    public function testMovingALinkBelowTheLimitIsRefused(): void
    {
        $this->limit(['Header' => 2]);
        $this->nest('header-2', 'header-1');

        $source = $this->source();

        $this->expectException(LogicException::class);

        $source->moveNode($this->item($source, 'header-3'), $this->item($source, 'header-2'), 0);
    }

    public function testMovingALinkWithChildrenCannotPushThemBelowTheLimit(): void
    {
        $this->limit(['Header' => 2]);
        $this->nest('header-3', 'header-2');

        $source = $this->source();

        // header-2 is allowed under header-1 on its own, but its child would land on level 3
        $this->expectException(LogicException::class);

        $source->moveNode($this->item($source, 'header-2'), $this->item($source, 'header-1'), 0);
    }

    public function testMovingWithinTheLimitIsAllowed(): void
    {
        $this->limit(['Header' => 2]);

        $source = $this->source();
        $source->moveNode($this->item($source, 'header-2'), $this->item($source, 'header-1'), 0);

        $this->assertSame(2, MenuItem::get()->byID($this->item($source, 'header-2')->ID)->getMenuLevel());
    }

    public function testAOneLevelMenuIsFlat(): void
    {
        $this->limit(['Header' => 1]);

        $source = $this->source();

        $this->assertTrue($source->canAddChildren(null), 'Top level links can still be added');
        $this->assertFalse($source->canAddChildren($this->item($source, 'header-1')));
    }

    public function testTheTreeFieldIsGivenTheMenuLimit(): void
    {
        $this->limit(['Header' => 2]);

        $set = $this->objFromFixture(MenuSet::class, 'header');
        $tree = $set->getCMSFields()->dataFieldByName('MenuItems');

        $this->assertInstanceOf(TreeField::class, $tree);
        $this->assertSame(2, $tree->getSchemaDataDefaults()['maxDepth']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidDepths(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'string' => ['2'],
            'float' => [1.5],
            'null' => [null],
        ];
    }

    #[DataProvider('invalidDepths')]
    public function testOnlyADepthOfOneOrMoreIsSupported(mixed $depth): void
    {
        $this->limit(['Header' => $depth]);

        $this->expectException(InvalidArgumentException::class);

        $this->objFromFixture(MenuSet::class, 'header')->getMaxMenuDepth();
    }

    public function testABadDepthFailsTheBuild(): void
    {
        $this->limit(['NotAMenuYet' => 0]);

        $this->expectException(InvalidArgumentException::class);

        MenuSet::singleton()->requireDefaultRecords();
    }
}
