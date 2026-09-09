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

    public function testSectionRendersTheTreeField(): void
    {
        $this->logInWithPermission(['ADMIN']);

        $response = $this->get('admin/menu-manager');

        $this->assertSame(200, $response->getStatusCode());

        $body = (string) $response->getBody();

        $this->assertStringContainsString('entwine-treefield', $body);
        $this->assertStringContainsString('data-schema-component="TreeField"', $body);
        $this->assertStringContainsString(MenuItemTreeSource::KEY, $body);
    }

    /**
     * Without cms-tabset and the CMS tab template, the tabs render as a bare strip inside the
     * form instead of in the section header.
     */
    public function testTabsUseTheCmsTabset(): void
    {
        $this->logInWithPermission(['ADMIN']);

        $body = (string) $this->get('admin/menu-manager')->getBody();

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
        $this->logInWithPermission(['ADMIN']);

        $body = (string) $this->get('admin/menu-manager')->getBody();

        $this->assertStringContainsString(
            'id="Form_EditForm_MenuItems_Holder" class="form-group field treefield form-group--no-label"',
            $body
        );
    }

    public function testTheMenuPickerIsRendered(): void
    {
        $this->logInWithPermission(['ADMIN']);

        $body = (string) $this->get('admin/menu-manager')->getBody();

        $this->assertStringContainsString('menu-admin__selector', $body);
        $this->assertStringContainsString('data-menu-admin-link', $body);
    }

    public function testEveryActionIsOffered(): void
    {
        $this->logInWithPermission(['ADMIN']);

        $body = (string) $this->get('admin/menu-manager')->getBody();

        foreach (['action_save', 'action_publish', 'action_addMenuSet', 'action_delete'] as $action) {
            $this->assertStringContainsString($action, $body);
        }
    }

    public function testTreeEndpointReturnsTheLinksOfOneMenu(): void
    {
        $this->logInWithPermission(['ADMIN']);

        $header = $this->objFromFixture(MenuSet::class, 'header');
        $response = $this->get(
            'admin/tree-field/tree/' . MenuItemTreeSource::KEY . '/' . $header->ID
        );

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $titles = array_column($payload['nodes'], 'title');

        $this->assertSame(['Header 1', 'Header 2', 'Header 3'], $titles);
        $this->assertNotContains('Footer 1', $titles);
    }

    public function testSectionIsNotReachableWithoutCmsAccess(): void
    {
        $this->logOut();
        $this->autoFollowRedirection = false;

        $response = $this->get('admin/menu-manager');

        $this->assertSame(302, $response->getStatusCode());
    }
}
