CodeSpotlightApplicationToolsBundle
===================================

A bundle which provides lots of tools, components, helpers and commands to make your life easier. Stay productive with these tools!

Which tools are ready to use?
-----------------------------

This is just the beginning. For now these are the tools that are ready to use:

* Application Control: Shutdown and initialize your app from the CLI with the commands provided by this component.
* Application Requirements: A really useful component which allows you to configure the requirements of your application using Closures. Then, running a simple command from the CLI you can have a beautiful list which highlights which requirements are OK, which have WARNINGS and which have ERRORS. The nice thing about this is that you can hook in an event fired by this component, so you can add requirements needed by your own bundles!
* Base classes: Some base classes with useful methods. You can extend these classes to help you out in your work. An example of these classes is the AbstractType class.
* Helper classes: These classes provides helper methods for all kind of things. Right now there's a TextHelper with useful methods to manipulate texts.


Which ones are under development right now?
-------------------------------------------

* Service Layer: A version of my ApplicationServiceAbstractBundle with steroids. It will be more decoupled, with forms handling and lots of new features.
* Cron component: A really useful and easy way to handle multiple cron jobs in one command. This component fires an event to which your bundles can hook on. And it provides a way to persist information about the jobs, schedule and configure them using the ORM. Just hook your jobs in the cron event, create a cron job with: php app/console cron:run , and forget about running multiple commands.

Which ones are planned?
-----------------------

There's a lot of things I want to add here. And advices and ideas are welcome.

Installation
------------

Clone the bundle: ::

    git clone git://github.com/comfortablynumb/CodeSpotlightApplicationToolsBundle.git vendor/bundles/CodeSpotlight/Bundle/ApplicationToolsBund$

Then modify your autoload.php: ::

    // autoload.php
    $loader->registerNamespaces(array(
        // Rest of vendors..

        'CodeSpotlight' => __DIR__.'/../vendor/bundles'
    ));

Register the bundle in your AppKernel.php: ::

    // AppKernel.php
    public function registerBundles()
    {
        $bundles = array(
            // Rest of bundles..
            new CodeSpotlight\Bundle\ApplicationToolsBundle\CodeSpotlightApplicationToolsBundle()
        );
    }

