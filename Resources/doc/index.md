# Documentation

## Requirements

This bundle requires Symfony 2.3+

## Installation

### Step 1: Download EoKeyClientBundle using composer

Add EoKeyClientBundle in your composer.json:

```
{
    "require": {
        "eo/keyclient-bundle": "dev-master"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/eo/EoKeyClientBundle"
        }
    ]
}
```

Now tell composer to download the bundle by running the command:
```
$ php composer.phar update eo/keyclient-bundle
```
Composer will install the bundle to your project's vendor/eo directory.

### Step 2: Enable the bundle

Enable the bundle in the kernel:

```
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = parent::registerBundles();

    $bundles[] = new Eo\KeyClientBundle\EoKeyClientBundle();

    return $bundles;
}
```

### Step 3: Configure the EoKeyClientBundle

Now that you have properly installed and enabled EoKeyClientBundle, the next step is to configure the bundle to work with the specific needs of your application.

Add the following configuration to your config.yml file
```
# app/config/config.yml
eo_key_client:
    alias:  CHANGE-THIS
    secret: CHANGE-THIS
```