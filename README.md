#Heyday Menu Manager

The menu management module is for creating custom menu structures when the site
tree hierarchy just won't do.

##License

Flexible fields is licensed under an [MIT license](http://heyday.mit-license.org/)

##Installation

###Non-composer

To install just drop the silverstripe-menumanager directory into your SilverStripe root and run a /dev/build/?flush=1

###Composer

Installing from composer is easy, 

Create or edit a `composer.json` file in the root of your SilverStripe project, and make sure the following is present.

```json
{
    "require": {
        "heyday/silverstripe-menumanager": "*"
    }
}
```

After completing this step, navigate in Terminal or similar to the SilverStripe root directory and run `composer install` or `composer update` depending on whether or not you have composer already in use.

##Usage
There are 2 main steps to creating a menu using menu management.

1. Create a new MenuSet
2. Add MenuItems to that MenuSet

### Creating a MenuSet ###

This is pretty straight forward. You just give the MenuSet a Name (which is what
you reference in the templates when controlling the menu)


### Creating MenuItems ###

Once you have saved your MenuSet you can add MenuItems.

MenuItems have 5 important fields:

1. Page
2. MenuTitle
3. Link
4. Sort
5. IsNewWindow


#### Page ####
A page to associate your MenuItem with.


#### MenuTitle ####
You can enter a custom MenuTitle in this field. If left blank the MenuTitle will
automatically be pulled from the associated Page.
A hidden feature of this field is that if you name the MenuTitle the same as a
different MenuSets Name then you can use the MenuSetChildren method to have
nested MenuSets. Example:

	<% control MenuSet(YourMenuName) %>
		<a href="$Link" class="$LinkingMode">$MenuTitle</a>
		<% control MenuSetChildren %>
			<a href="$Link" class="$LinkingMode">$MenuTitle</a>
		<% end_control %>
	<% end_control %>


#### Link ####
This field can be left blank unless you want to link to an external website.
When left blank using $Link in templates will automatically pull the link from
the MenuItems associated Page.
If you enter a link in this field and then pick a Page as well the link will
be overwritten by the Page you chose.


#### Sort ####
Used to sort MenuItems when DataObjectManager is not in use.


#### IsNewWindow ####
Can be used as a check to see if 'target="_blank"' should be added to links.


###Code guidelines

This project follows the standards defined in:

* [PSR-1](https://github.com/pmjones/fig-standards/blob/psr-1-style-guide/proposed/PSR-1-basic.md)
* [PSR-2](https://github.com/pmjones/fig-standards/blob/psr-1-style-guide/proposed/PSR-2-advanced.md)



