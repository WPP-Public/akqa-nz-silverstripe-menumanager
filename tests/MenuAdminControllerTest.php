<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\MenuSet;
use Heyday\MenuManager\TreeField\MenuItemTreeSource;
use SilverStripe\Dev\FunctionalTest;

/**
 * Requests the Menus section the way a browser does, so a fatal in the admin or in the field's
 * template shows up as a failing test rather than a blank screen in the CMS.
 */
class MenuAdminControllerTest extends FunctionalTest
{
    protected static $fixture_file = 'MenuTest.yml';

    private function openMenuUrl(): string
    {
        return 'admin/menu-manager?MenuSetID='
            . $this->objFromFixture(MenuSet::class, 'header')->ID;
    }

    private function bodyFor(string $url): string
    {
        $this->logInWithPermission(['ADMIN']);

        $response = $this->get($url);
        $this->assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    public function testTheSectionOpensOnTheListOfMenus(): void
    {
        $body = $this->bodyFor('admin/menu-manager');

        $this->assertStringContainsString('menu-admin__grid', $body);
        $this->assertStringContainsString('menu-tile__title', $body);
        $this->assertStringNotContainsString('entwine-treefield', $body);
    }

    public function testATileLinksStraightToItsMenu(): void
    {
        $footer = $this->objFromFixture(MenuSet::class, 'footer');
        $body = $this->bodyFor('admin/menu-manager');

        $this->assertStringContainsString(
            'href="/admin/menu-manager?MenuSetID=' . $footer->ID . '"',
            $body
        );
    }

    public function testATileFlagsUnpublishedChanges(): void
    {
        $body = $this->bodyFor('admin/menu-manager');

        $this->assertStringContainsString('menu-tile--draft', $body);
        $this->assertStringContainsString('menu-tile__status', $body);
    }

    public function testOpeningAMenuRendersTheTreeField(): void
    {
        $body = $this->bodyFor($this->openMenuUrl());

        $this->assertStringContainsString('entwine-treefield', $body);
        $this->assertStringContainsString('data-schema-component="TreeField"', $body);
        $this->assertStringContainsString(MenuItemTreeSource::KEY, $body);
    }

    public function testOpeningAMenuScopesTheTreeToIt(): void
    {
        $footer = $this->objFromFixture(MenuSet::class, 'footer');
        $body = $this->bodyFor('admin/menu-manager?MenuSetID=' . $footer->ID);

        $this->assertStringContainsString('data-scope-id="' . $footer->ID . '"', $body);
    }

    public function testOpeningAMenuOffersAWayBack(): void
    {
        $body = $this->bodyFor($this->openMenuUrl());

        $this->assertStringContainsString('menu-admin__back', $body);
    }

    /**
     * Without cms-tabset and the CMS tab template, the tabs render as a bare strip inside the
     * form instead of in the section header.
     */
    public function testTabsUseTheCmsTabset(): void
    {
        $body = $this->bodyFor($this->openMenuUrl());

        $this->assertMatchesRegularExpression('/class="[^"]*cms-tabset[^"]*"/', $body);
        $this->assertStringNotContainsString('ss-tabset field CompositeField tabset', $body);
        $this->assertStringContainsString('id="Root_Settings"', $body);
        $this->assertStringContainsString('id="Root_History"', $body);
    }

    /**
     * A label column would narrow the tree, which needs the full width of the panel.
     */
    public function testTheTreeHasNoLabelColumn(): void
    {
        $body = $this->bodyFor($this->openMenuUrl());

        $this->assertStringContainsString(
            'id="Form_EditForm_MenuItems_Holder" class="form-group field treefield form-group--no-label"',
            $body
        );
    }

    public function testEveryActionIsOffered(): void
    {
        $body = $this->bodyFor($this->openMenuUrl());

        foreach (['action_save', 'action_publish', 'action_addMenuSet', 'action_delete'] as $action) {
            $this->assertStringContainsString($action, $body);
        }
    }

    public function testTheTreeEndpointReturnsTheLinksOfOneMenu(): void
    {
        $header = $this->objFromFixture(MenuSet::class, 'header');
        $body = $this->bodyFor(
            'admin/tree-field/tree/' . MenuItemTreeSource::KEY . '/' . $header->ID
        );

        $payload = json_decode($body, true);
        $titles = array_column($payload['nodes'], 'title');

        $this->assertSame(['Header 1', 'Header 2', 'Header 3'], $titles);
        $this->assertNotContains('Footer 1', $titles);
    }

    public function testSectionIsNotReachableWithoutCmsAccess(): void
    {
        $this->logOut();
        $this->autoFollowRedirection = false;

        $this->assertSame(302, $this->get('admin/menu-manager')->getStatusCode());
    }
}
