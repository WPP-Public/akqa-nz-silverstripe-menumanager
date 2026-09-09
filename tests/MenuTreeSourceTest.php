<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\TreeField\MenuTreeSource;
use LogicException;
use SilverStripe\Dev\SapphireTest;

/**
 * The menu tree holds two classes at once, so most of what matters here is the boundary between
 * them: what may sit at the top level, what an identifier is allowed to reach, and what happens
 * to a branch dragged from one set to another.
 */
class MenuTreeSourceTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    private MenuTreeSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInWithPermission(['MANAGE_MENU_SETS', 'MANAGE_MENU_ITEMS']);
        $this->source = MenuTreeSource::create();
    }

    private function set(string $fixture): MenuSet
    {
        return $this->objFromFixture(MenuSet::class, $fixture);
    }

    private function item(string $fixture): MenuItem
    {
        return $this->objFromFixture(MenuItem::class, $fixture);
    }

    public function testTreeNestsItemsUnderTheirSet(): void
    {
        $tree = $this->source->getTree();

        $this->assertCount(2, $tree);
        $this->assertSame('Header', $tree[0]['title']);
        $this->assertCount(3, $tree[0]['children']);
        $this->assertSame('Header 1', $tree[0]['children'][0]['title']);
    }

    public function testIdentifiersAreNamespacedByType(): void
    {
        $set = $this->set('header');
        $item = $this->item('header-1');

        $this->assertSame('set-' . $set->ID, $this->source->getNodeID($set));
        $this->assertSame('item-' . $item->ID, $this->source->getNodeID($item));
    }

    public function testAnIdentifierCannotReachTheWrongClass(): void
    {
        $item = $this->item('header-1');

        $this->assertInstanceOf(MenuItem::class, $this->source->getNode('item-' . $item->ID));
        $this->assertNull(
            $this->source->getNode('set-' . $item->ID . '0000'),
            'A set identifier must not resolve to a menu item'
        );
        $this->assertNull($this->source->getNode((string) $item->ID));
        $this->assertNull($this->source->getNode('item-abc'));
    }

    public function testOnlySetsMayLiveAtTheTopLevel(): void
    {
        $this->assertTrue($this->source->allowsRoot($this->set('header')));
        $this->assertFalse($this->source->allowsRoot($this->item('header-1')));
    }

    public function testAddingAtTheTopLevelCreatesASet(): void
    {
        $created = $this->source->createNode(null);

        $this->assertInstanceOf(MenuSet::class, $created);
        $this->assertNotEmpty($created->Name);
    }

    public function testNewSetNamesDoNotCollide(): void
    {
        $first = $this->source->createNode(null);
        $second = MenuTreeSource::create()->createNode(null);

        $this->assertNotSame($first->Name, $second->Name);
    }

    public function testAddingUnderASetCreatesAnItemInThatSet(): void
    {
        $set = $this->set('footer');
        $created = $this->source->createNode($set);

        $this->assertInstanceOf(MenuItem::class, $created);
        $this->assertSame((int) $set->ID, (int) $created->MenuSetID);
        $this->assertSame(0, (int) $created->ParentItemID);
    }

    public function testAddingUnderAnItemNestsInsideTheSameSet(): void
    {
        $parent = $this->item('header-1');
        $created = $this->source->createNode($parent);

        $this->assertSame((int) $parent->MenuSetID, (int) $created->MenuSetID);
        $this->assertSame((int) $parent->ID, (int) $created->ParentItemID);
    }

    public function testASetCannotBeMovedInsideAnything(): void
    {
        $this->expectException(LogicException::class);
        $this->source->moveNode($this->set('footer'), $this->set('header'), 0);
    }

    public function testAnItemCannotBeMovedToTheTopLevel(): void
    {
        $this->expectException(LogicException::class);
        $this->source->moveNode($this->item('header-1'), null, 0);
    }

    public function testMovingAnItemBetweenSetsTakesItsChildrenWithIt(): void
    {
        $parent = $this->item('header-1');
        $child = $this->item('header-2');
        $child->ParentItemID = $parent->ID;
        $child->write();

        $footer = $this->set('footer');

        MenuTreeSource::create()->moveNode(
            MenuItem::get()->byID($parent->ID),
            $footer,
            0
        );

        $this->assertSame((int) $footer->ID, (int) MenuItem::get()->byID($parent->ID)->MenuSetID);
        $this->assertSame(
            (int) $footer->ID,
            (int) MenuItem::get()->byID($child->ID)->MenuSetID,
            'A nested item follows its parent into the new set'
        );
    }

    public function testMovingAnItemInsideItselfIsRejected(): void
    {
        $parent = $this->item('header-1');
        $child = $this->item('header-2');
        $child->ParentItemID = $parent->ID;
        $child->write();

        $source = MenuTreeSource::create();

        $this->expectException(LogicException::class);
        $source->moveNode(
            $source->getNode('item-' . $parent->ID),
            $source->getNode('item-' . $child->ID),
            0
        );
    }

    public function testNestingDeeperThanFourLevelsIsRejected(): void
    {
        $level2 = $this->item('header-1');
        $level3 = $this->item('header-2');
        $level3->ParentItemID = $level2->ID;
        $level3->write();

        $level4 = $this->item('header-3');
        $level4->ParentItemID = $level3->ID;
        $level4->write();

        $source = MenuTreeSource::create();

        $this->assertFalse($source->canAddChildren($source->getNode('item-' . $level4->ID)));
        $this->assertTrue($source->canAddChildren($source->getNode('item-' . $level3->ID)));
    }

    public function testReorderingSetsRenumbersThem(): void
    {
        $footer = $this->set('footer');
        $this->source->moveNode($footer, null, 0);

        $titles = array_column(MenuTreeSource::create()->getTree(), 'title');

        $this->assertSame(['Footer 1', 'Header'], $titles);
    }

    public function testDeletingASetRemovesItsItems(): void
    {
        $set = $this->set('header');
        $itemID = $this->item('header-1')->ID;

        $this->source->deleteNode($set);

        $this->assertNull(MenuSet::get()->byID($set->ID));
        $this->assertNull(MenuItem::get()->byID($itemID));
    }

    public function testTheDetailFormDoesNotNestATreeInsideItself(): void
    {
        $form = $this->source->getNodeForm(
            $this->set('header'),
            'TestForm',
            \SilverStripe\Control\Controller::curr()
        );

        $this->assertNull($form->Fields()->dataFieldByName('MenuItems'));
    }

    public function testStructuralFieldsAreProtectedFromTheDetailForm(): void
    {
        $protected = $this->source->getProtectedFields();

        $this->assertContains('Sort', $protected);
        $this->assertContains('ParentItemID', $protected);
        $this->assertContains('MenuSetID', $protected);
    }
}
