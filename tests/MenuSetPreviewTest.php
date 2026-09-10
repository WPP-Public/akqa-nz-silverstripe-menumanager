<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuSet;
use SilverStripe\Admin\Navigator\SilverStripeNavigator;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\CMSPreviewable;

/**
 * A menu is not a page, but it renders on every page, so the CMS preview opens the site itself.
 */
class MenuSetPreviewTest extends SapphireTest
{
    protected static $fixture_file = 'MenuTest.yml';

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInWithPermission(['ADMIN']);
    }

    private function header(): MenuSet
    {
        return $this->objFromFixture(MenuSet::class, 'header');
    }

    public function testMenusArePreviewable(): void
    {
        $this->assertInstanceOf(CMSPreviewable::class, $this->header());
        $this->assertSame('text/html', $this->header()->getMimeType());
    }

    public function testThePreviewOpensTheSite(): void
    {
        $link = $this->header()->PreviewLink();

        $this->assertNotEmpty($link);
        $this->assertStringStartsWith('http', $link);
    }

    public function testThePreviewPageCanBeConfigured(): void
    {
        Config::modify()->set(MenuSet::class, 'preview_url', 'https://example.com/about-us');

        $this->assertSame('https://example.com/about-us', $this->header()->PreviewLink());
    }

    public function testThereIsNoPreviewWithoutViewPermission(): void
    {
        $this->logOut();

        $this->assertNull($this->header()->PreviewLink());
    }

    public function testTheEditLinkOpensTheMenuInTheCms(): void
    {
        $set = $this->header();

        $this->assertStringContainsString('menu-manager', $set->getCMSEditLink());
        $this->assertStringContainsString('MenuSetID=' . $set->ID, $set->getCMSEditLink());
        $this->assertNull(MenuSet::create()->getCMSEditLink());
    }

    private function navigatorTitles(MenuSet $set): array
    {
        $titles = [];

        foreach ((new SilverStripeNavigator($set))->getItems() as $item) {
            $titles[] = $item->getTitle();
        }

        return $titles;
    }

    public function testAnUnpublishedMenuOnlyOffersDraft(): void
    {
        $titles = $this->navigatorTitles($this->header());

        $this->assertContains('Draft', $titles);
        $this->assertNotContains('Published', $titles);
    }

    public function testAPublishedMenuOffersBothStates(): void
    {
        $set = $this->header();
        $set->publishRecursive();

        $titles = $this->navigatorTitles(MenuSet::get()->byID($set->ID));

        $this->assertContains('Draft', $titles);
        $this->assertContains('Published', $titles);
    }
}
