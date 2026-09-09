# Silverstripe Menu Manager

The menu management module is for creating custom menu structures when the site
tree hierarchy just won't do.

## Installation

```sh
composer require heyday/silverstripe-menumanager
```

Run `dev/build` afterwards: nested links add a `ParentItemID` column to
`MenuItem`.

After completing this step, navigate in Terminal or similar to the SilverStripe
root directory and run `composer install` or `composer update` depending on
whether or not you have composer already in use.

## Usage

The `Menus` section of the CMS shows every menu set as one tree, with that set's
links nested underneath it. Sets and links are added, renamed, re-ordered,
nested and deleted in place, and whatever is selected has its own fields open
alongside the tree.

* **Add menu** creates a new set at the top level.
* The **+** on a row adds a link inside it, up to three levels of links deep.
* Rows are re-ordered and nested by dragging, or from the row's own menu, which
  also offers move up, move down, indent and outdent for keyboard use.
* Deleting a row deletes everything nested under it, after a confirmation that
  says so.

The tree is provided by [akqa/silverstripe-tree-field](https://github.com/WPP-Public/akqa-silverstripe-tree-field),
which this module requires.

### Creating a MenuSet

Open the `Menus` section and choose **Add menu**, then give the set a Name,
which is what you reference in templates when rendering the menu.

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

Select a menu set in the tree and use the **+** on its row to add a link. Use
the **+** on a link to nest another link inside it.

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

Can be used as a check to see if 'target="\_blank"' should be added to links.

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

`$MenuItems` returns every link in the set regardless of nesting, which is what
a flat menu wants:

```html
<% loop $MenuSet('YourMenuName').MenuItems %>
<a href="{$URL}" class="{$LinkingMode}">{$MenuTitle}</a>
<% end_loop %>
```

For a nested menu, loop `$RootMenuItems` and then `$Children` on each link.
Swapping a flat menu over to `$RootMenuItems` changes nothing until someone
nests a link, and prevents nested links appearing twice once they do:

```html
<% loop $MenuSet('YourMenuName').RootMenuItems %>
<a href="{$URL}" class="{$LinkingMode}">{$MenuTitle}</a>
<% if $Children %>
<ul>
    <% loop $Children %>
    <li><a href="{$URL}" class="{$LinkingMode}">{$MenuTitle}</a></li>
    <% end_loop %>
</ul>
<% end_if %>
<% end_loop %>
```

To loop through _all_ MenuSets and their items:

```html
<% loop $MenuSets %> <% loop $MenuItems %>
<a href="{$URL}" class="{$LinkingMode}">{$MenuTitle}   </a>
<% end_loop %> <% end_loop %>
```

Optionally you can also limit the number of MenuSets and MenuItems that are
looped through.

The example below will fetch the top 4 MenuSets (as seen in Menu Management),
and the top 5 MenuItems for each:

```html
<% loop $MenuSets.Limit(4) %> <% loop $MenuItems.Limit(5) %>
<a href="{$URL}" class="{$LinkingMode}">{$MenuTitle}</a>
<% end_loop %> <% end_loop %>
```

#### Enabling partial caching

[Partial caching](https://docs.silverstripe.org/en/4/developer_guides/performance/partial_caching/)
can be enabled with your menu to speed up rendering of your templates.

```html
<% with $MenuSet('YourMenuName') %>
<% cached 'YourMenuNameCacheKey', $LastEdited, $MenuItems.max('LastEdited'), $MenuItems.count %>
<% if $MenuItems %>
<nav>
    <% loop $MenuItems %>
    <a href="{$URL}" class="{$LinkingMode}"> $MenuTitle.XML </a>
    <% end_loop %>
</nav>
<% end_if %>
<% end_cached %>
<% end_with %>
```

### Sorting menu sets

Menu sets are dragged into order in the tree like anything else, so the old
`allow_sorting` setting is gone and no configuration is needed.

## Subsite Support

If you're using SilverStripe Subsites, you can make MenuManager subsite aware
via applying an extension to the MenuSet.

_app/\_config/menus.yml_

```yaml
Heyday\MenuManager\MenuSet:
    create_menu_sets_per_subsite: true
    extensions:
        - Heyday\MenuManager\Extensions\MenuSubsiteExtension
Heyday\MenuManager\MenuItem:
    extensions:
        - Heyday\MenuManager\Extensions\MenuSubsiteExtension
```

## License

Menu Manager is licensed under an [MIT license](http://heyday.mit-license.org/)
