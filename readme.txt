=== WPChef ===
Contributors: wpchefgadget
Requires at least: 4.2
Tested up to: 4.9.5
Stable tag: 2.1.2

Quickly set up a preconfigured WordPress site or expand an existing one using a recipe which is a set of plugins, options, themes and content pieces.

== Description ==
WPChef's "Recipes" are a new concept in WordPress automation.

A recipe is a predefined set of plugins, settings, content pieces and other WordPress items that can be deployed in minutes. The WPChef plugin provides an easy visual way of creating, installing, and sharing recipes.

**Scenario 1**
Say you had a fully configured WordPress site with a number of plugins, a theme, menu items, pages, and a specific configuration of settings. A new site you want to develop would benefit from the same configuration. Using WPChef you can create a recipe with the configuration from the first site and apply it to the new site. The new site is now set up and ready to go in minutes, not hours or days. Setting up new sites in this way can save hours of development time and money. You can share your recipes with others, or even sell them.

**Scenario 2**
Say you've just started your new WordPress blog and you're not sure what plugins you need. With WPChef you can simply pick a recommended recipe from the WPChef recipes directory and apply it to your site. An example would be the "Bare Minimum" recipe that will supercharge your site with a set of essential plugins every WP site is recommended to have - from a handy WYSIWYG widget editor to an easy to use SEO plugin. You can explore the recipes directory and find the perfect recipe for your needs.

The potential is endless - you can configure sites for particular niches, sell your recipes, configure special recipes for your clients, and cut hours of development from your deployment process.

== WPChef In Detail ==

WPChef consists of 3 parts:

* Recipes
* WPChef plugin
* WPChef.org directory

= Recipes =
WPChef works through recipes. A recipe is a small file in JSON format. It contains a list of plugins, themes, options and actions that can be applied to a WordPress site one after another, fast and in an automated manner. By activating a recipe all of its components (we call them "ingredients") get installed. During the activation process a user has full control over which ingredients should be installed and which not. Here's a more detailed description of ingredients:
* **Plugins**: A recipe can install any number of WordPress plugins specified in a recipe. Only plugins from the oficial WordPress.org directory are allowed. Each plugin inside a recipe is represented by its slug. I.e. a recipe is not a package with plugins archived inside it but a text reference to known WordPress plugins. This makes a recipe very compact in size (bytes and kilobytes). Upon recipe installation WPChef connects to WordPress.org and attempts to install a plugin specified by its slug, just like with a native WordPress plugins installation process. After a plugin is installed, recipe installation moves to the next step.
* **Themes**: Themes are handled a similar way to plugins. Only themes from WordPress.org are allowed. After a theme is installed, it gets activated right away which means that the current theme on the site will be deactivated. If there are a number of themes specified in a recipe, all of them will be installed, but only the last one will be activated since only one theme can be active at a time.
* **Options**: Options are WordPress settings. They can be core settings, plugin settings and theme settings. If a recipe sets a new option value and this option is already present on the site, it will be overwritten with that new value. A backup of the current value will be created in case a recipe needs to be rolled back. WPChef supports options of complex structure like nested arrays as well as plain strings and booleans. During recipe installation every option can be manually edited or skipped. Here is an example of how setting options can be helpful: Let's say your recipe installs a SEO plugin with turned off sitemap functionality by default. By specifying an option that is responsible for the sitemap functionality in this plugin the recipe can automatically activate the sitemap during recipe installation.
* **Actions**: It is common after a new WordPress site installation for standard one-time operations to be completed, like creating a page or a menu item, registering a test user, and so on. In WPChef these one-time operations are called "Actions". You can specify when an action should be run - either on recipe activation or deactivation. In both cases it will only run once. For example, you can create a house cleaning recipe that will deactivate all obsolete plugins that you don't need anymore, and this will be done via Actions during recipe activation.
Recipes are managed by the WPChef plugin and hosted at WPChef directory or as local files.

= WPChef Plugin =
The WPChef plugin is the core of the system. It provides all needed functionality to operate Recipes:
* **Recipe creation**: The WPChef plugin provides an easy visual way to create recipes via its built-in recipe architect. All types of ingredients can be created and configured from here. You can also specify what order they will be installed during recipe activation in the future.  Aside from ingredients, you can specify a name of the recipe, a short description and other meta information, including the required PHP version to safely run all ingredients of the recipe. After a recipe is created, it can be applied to the site right away which makes it easy to test.
* **Recipe installation, removal and updates**: The WPChef plugin provides a way to install recipes that any WordPress user is familiar with because it is similar to how WordPress plugins are installed. You click the "Add Recipe" link in the menu to get to the recipes search page where you can find a recipe that fits your needs. The list of recipes is pulled from the WPChef directory on wpchef.org, where all recipes are hosted. Before picking a recipe you can read what the recipe is about, who created it, what ingredient it contains, what rating it has, and how new it is. By clicking the "Activate" button a popup window with ready-to-start installation process appears. Here you can see what ingredients will be installed and turn off any of them. The options can also be edited. You can also specify if a recipe should be automatically updated in the future or not. Clicking the "Activate" button in this window will start the installation process. It is visual and you can see what happens through all stages. After a recipe is installed, you will get a success message. Now you can see all your installed recipes on a separate page which is very similar to the Plugins page. At any time a recipe can be deactivated. This will roll back the site to its previous state by deactivating all installed (by the recipe) plugins and restoring all changed (by the recipe) options to their backed up values. Deactivation of a recipe is a visual user-controlled process just like the activation.
* **Uploading a recipe to the recipes directory**: WPChef allows you to publish recipes made by you to the wpchef.org recipes directory. In order to do that you need to create a private account at wpchef.org and connect it with your WPChef instance. It can be done right from the WPChef plugin settings page. By clicking the "Connect" button there you are prompted to establish a connection with wpchef.org. You are asked to enter your email address and create a password to complete registration at wpchef.org and then your WPChef plugin will get linked with your private wpchef.org account. This is done via OAuth 2.0 protocol over a secure https connection. Initially a recipe uploaded to wpchef.org is made private. Only its author can use that private recipe and they will not be available to other users. The author of the recipe also may request publishing their recipes in the public wpchef.org directory. Our moderators will validate the recipe and publish it or will explain how it should be adjusted to be allowed to become public. Creating an account at wpchef.org is optional and is only needed to operate with private recipes or publish your own recipes. If this is not your plan there's no need to create an account - the plugin will work without one.

= WPChef.org Directory =
The WPChef Directory is located at wpchef.org. Its main functions are:
* **Host public recipes that are available to any WPChef plugin user**. Recipes are organized into a directory with navigation by categories. The WPChef plugin queries the directory in order to show available recipes on the "Add Recipe" plugin's page and search through them. During a recipe installation it gets downloaded from the directory and stored locally on your WordPress site. When an updated version of a recipe is added on wpchef.org, the WPChef plugin will download it as well as automatically update it if this option is turned on. The WPChef plugin will check for new versions twice a day.
* **Host private recipes**. The private recipes are available to their authorized owners only. They are stored at wpchef.org in private user accounts and are available for personal use only.
* wpchef.org provides a support forum for recipe users, documentation and a way to contact the WPChef team.

= Site Management =
WPChef provides a simple site management system at wpchef.org that allows you to manage all your WPChef installations from a single place. The management system allows you to see what recipes, plugins and themes are installed on your sites and their versions. The management system is secure and provides the option via the settings page to opt out on an installation basis should the user want a specific installation to be unavailable for site management.

= Legal Information =
The WPChef plugin doesn't send any personal information to wpchef.org. When a user decides to create a private account at wpchef.org, they are asked to enter a registration email and create a password. This information is not being pulled automatically from the site where the plugin is installed and it has to be entered manually. If you choose to manage your sites via the WPChef Site Management system, a list of installed plugins, themes and recipes will be sent to wpchef.org. During API queries to wpchef.org the site's URL is being sent as well. WPChef uses it to create authorization tokens when a user decides to link the plugin with the wpchef.org private account. We value the privaсy of users and do everything we can to protect it.

== Changelog ==

= 2.1.2 =
* Implemented a simple site management system.
* Some hooks added for the future ability of adding new custom ingredients.
* Refactoring done.

= 2.0.1 =
* Initial release.