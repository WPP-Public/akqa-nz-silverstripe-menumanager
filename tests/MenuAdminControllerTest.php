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
