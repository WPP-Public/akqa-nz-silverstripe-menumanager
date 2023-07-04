# Silverstripe Menu Manager

The menu management module is for creating custom menu structures when the site
tree hierarchy just won't do.

The latest version only supports SilverStripe 4, see the 2.0 branch for a
SilverStripe 3.x compatible version.

## License

Menu Manager is licensed under an [MIT license](http://heyday.mit-license.org/)

## Installation

```
composer require heyday/silverstripe-menumanager "^3.0"
```

After completing this step, navigate in Terminal or similar to the SilverStripe
root directory and run `composer install` or `composer update` depending on
whether or not you have composer already in use.

## Usage

There are 2 main steps to creating a menu using menu management.

1. Create a new MenuSet
2. Add MenuItems to that MenuSet

### Creating a MenuSet

This is pretty straight forward. You just give the MenuSet a Name (which is what
you reference in the templates when controlling the menu).

As it is common to reference MenuSets by name in templates, you can configure
sets to be created automatically during the /dev/build task. These sets cannot
be deleted through the CMS.

```yaml
Heyday\MenuManager\MenuSet:
  default_sets:
    - Main
    - Footer
```


### Creating MenuItems

Once you have saved your MenuSet you can add MenuItems.

MenuItems have 4 important fields:

1. Page
2. MenuTitle
3. Link
4. IsNewWindow

#### Page

A page to associate your MenuItem with.

#### MenuTitle

This field can be left blank if you link the menu item with a page. If not fill
with the title you want to display in the template.

#### Link

This field can be left blank unless you want to link to an external website.
When left blank using $Link in templates will automatically pull the link from
the MenuItems associated Page. If you enter a link in this field and then pick a
Page as well the link will be overwritten by the Page you chose.

#### IsNewWindow

Can be used as a check to see if 'target="_blank"' should be added to links.

### Disable creating Menu Sets in the CMS

Sometimes the defined `default_sets` are all the menu's a project needs. You can
disable the ability to create new Menu Sets in the CMS:

```yml
Heyday\MenuManager\MenuAdmin:
  enable_cms_create: false
```

_Note: Non-default Menu Sets can still be deleted, to help tidy unwanted CMS
content._

### Usage in template

```html
	<% loop $MenuSet('YourMenuName').MenuItems %>
        <a href="{$Link}" class="{$LinkingMode}">{$MenuTitle}</a>
    <% end_loop %>
```

To loop through *all* MenuSets and their items:

	<% loop $MenuSets %>
		<% loop $MenuItems %>
			<a href="$Link" class="$LinkingMode">$MenuTitle</a>
		<% end_loop %>
	<% end_loop %>

Optionally you can also limit the number of MenuSets and MenuItems that are looped through.

The example below will fetch the top 4 MenuSets (as seen in Menu Management), and the top 5 MenuItems for each:

	<% loop $MenuSets.Limit(4) %>
		<% loop $MenuItems.Limit(5) %>
			<a href="$Link" class="$LinkingMode">$MenuTitle</a>
		<% end_loop %>
	<% end_loop %>

#### Enabling partial caching

[Partial caching](https://docs.silverstripe.org/en/4/developer_guides/performance/partial_caching/)
can be enabled with your menu to speed up rendering of your templates.

```html
	<% with $MenuSet('YourMenuName') %>
    <% cached 'YourMenuNameCacheKey', $LastEdited, $MenuItems.max('LastEdited'), $MenuItems.count %>
    <% if $MenuItems %>
    <nav>
        <% loop $MenuItems %>
            <a href="{$Link}" class="{$LinkingMode}"> $MenuTitle.XML </a>
        <% end_loop %>
    </nav>
    <% end_if %>
    <% end_cached %>
    <% end_with %>
```

### Allow sorting of MenuSets

By default menu sets cannot be sorted, however, you can set your configuration to allow it.

```yaml
Heyday\MenuManager\MenuSet:
  allow_sorting: true
```


### Code guidelines

This project follows the standards defined in:

* [PSR-1](http://www.php-fig.org/psr/psr-1/)
* [PSR-2](http://www.php-fig.org/psr/psr-2/)



