<?php

namespace Heyday\MenuManager\Test;

use Heyday\MenuManager\TreeField\MenuTreeSource;
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
        $this->assertStringContainsString(MenuTreeSource::KEY, $body);
    }

    public function testTreeEndpointReturnsSetsWithTheirItems(): void
    {
        $this->logInWithPermission(['ADMIN']);

        $response = $this->get('admin/tree-field/tree/' . MenuTreeSource::KEY . '/0');

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $titles = array_column($payload['nodes'], 'title');

        $this->assertContains('Header', $titles);
        $this->assertSame('set-' . $this->objFromFixture(
            \Heyday\MenuManager\MenuSet::class,
            'header'
        )->ID, $payload['nodes'][0]['id']);
        $this->assertNotEmpty($payload['nodes'][0]['children']);
    }

    public function testSectionIsNotReachableWithoutCmsAccess(): void
    {
        $this->logOut();
        $this->autoFollowRedirection = false;

        $response = $this->get('admin/menu-manager');

        $this->assertSame(302, $response->getStatusCode());
    }
}
