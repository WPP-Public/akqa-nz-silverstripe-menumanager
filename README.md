# Heyday Menu Manager

The menu management module is for creating custom menu structures when the site tree hierarchy just won't do.

The latest version only supports SilverStripe 4, see the 2.0 branch for a SilverStripe 3.x compatible version.

## License

Menu Manager is licensed under an [MIT license](http://heyday.mit-license.org/)

## Installation

### Non-composer

To install drop the silverstripe-menumanager directory into your SilverStripe root. You must also install [silverstripe-gridfieldextensions](https://github.com/ajshort/silverstripe-gridfieldextensions) as it is a dependency for this module.

Once both of these are installed, you can run `dev/build?flush=1`

### Composer

Installing from composer is easy, 

Create or edit a `composer.json` file in the root of your SilverStripe project, and make sure the following is present.

```json
{
    "require": {
        "heyday/silverstripe-menumanager": "~3.0.0"
    }
}
```

After completing this step, navigate in Terminal or similar to the SilverStripe root directory and run `composer install` or `composer update` depending on whether or not you have composer already in use.

## Usage
There are 2 main steps to creating a menu using menu management.

1. Create a new MenuSet
2. Add MenuItems to that MenuSet

### Creating a MenuSet

This is pretty straight forward. You just give the MenuSet a Name (which is what you reference in the templates when controlling the menu).

As it is common to reference MenuSets by name in templates, you can configure sets to be created automatically during the /dev/build task. These sets cannot be deleted through the CMS.

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
This field can be left blank if you link the menu item with a page. If not fill with the title you want to display in the template.

#### Link
This field can be left blank unless you want to link to an external website.
When left blank using $Link in templates will automatically pull the link from
the MenuItems associated Page.
If you enter a link in this field and then pick a Page as well the link will
be overwritten by the Page you chose.


#### IsNewWindow
Can be used as a check to see if 'target="_blank"' should be added to links.


### Usage in template

	<% loop $MenuSet('YourMenuName').MenuItems %>
		<a href="$Link" class="$LinkingMode">$MenuTitle</a>
	<% end_loop %>


### Code guidelines

This project follows the standards defined in:

* [PSR-1](http://www.php-fig.org/psr/psr-1/)
* [PSR-2](http://www.php-fig.org/psr/psr-2/)



