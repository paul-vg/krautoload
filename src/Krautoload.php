<?php

class Krautoload {

  /**
   * @var \Krautoload\Adapter_ClassLoader_Interface
   */
  static protected $adapter;

  /**
   * Default bootstrap.
   *
   * @param array $options
   * @return \Krautoload\Adapter_ClassLoader_Interface
   * @throws Exception
   */
  static function start(array $options = array()) {

    if (isset(self::$adapter)) {
      throw new Exception("Krautoload::start() can be called only once.");
    }

    // Build default options.
    $options += array(
      'cache' => FALSE,
      'cache_prefix' => NULL,
      'introspection' => FALSE,
    );

    // Include the bare minimum we need before Krautoload can load its own.
    $basedir = dirname(__FILE__) . '/Krautoload';
    require_once $basedir . '/ClassLoader/Interface.php';
    require_once $basedir . '/ClassLoader/Abstract.php';
    require_once $basedir . '/ClassLoader/Pluggable/Interface.php';
    require_once $basedir . '/ClassLoader/Pluggable.php';
    require_once $basedir . '/NamespacePathPlugin/Interface.php';
    require_once $basedir . '/NamespacePathPlugin/ShallowPSR0.php';
    require_once $basedir . '/NamespacePathPlugin/ShallowPSR0/AllUnderscore.php';

    // Build the class loader.
    if (!empty($options['introspection'])) {
      // Build a fancy class loader that can also do class discovery.
      // This can be useful to reuse the registered path-namespace mappings for
      // class discovery.
      // This being said, it is always possible to create a NamespaceInspector
      // independently of the actively registered class loader.
      require_once $basedir . '/NamespaceInspector/Interface.php';
      require_once $basedir . '/NamespaceInspector/Pluggable/Interface.php';
      require_once $basedir . '/NamespaceInspector/Pluggable.php';
      $loader = new Krautoload\NamespaceInspector_Pluggable();
    }
    else {
      // Build a basic class loader, that can only do class loading.
      $loader = new Krautoload\ClassLoader_Pluggable();
    }

    // Wire up the class finder so it can find Krautoload classes.
    // Krautoload uses PSR-0 with only underscores after the package namespace.
    $plugin = new Krautoload\NamespacePathPlugin_ShallowPSR0_AllUnderscore();
    $loader->addNamespacePlugin('Krautoload' . DIRECTORY_SEPARATOR, $basedir . DIRECTORY_SEPARATOR, $plugin);

    // Register the loader to the spl stack.
    $loader->register();

    // Create the adapter.
    if (!empty($options['introspection'])) {
      $adapter = new Krautoload\Adapter_NamespaceInspector_Pluggable($loader);
    }
    else {
      $adapter = new Krautoload\Adapter_ClassLoader_Pluggable($loader);
    }

    // Enable the cache, if any.
    switch ($options['cache']) {
      case 'ApcCache':
      case 'XCache':
      case 'WinCache':
        if (isset($options['cache_prefix'])) {
          // Load one more class that we will need.
          require_once $basedir . '/InjectedAPI/ClassFinder/LoadClassGetFile.php';
          // Build the cache decorator object.
          $cachedLoaderClass = 'Krautoload\ClassLoader_Cached_' . $options['cache'];
          /**
           * @var \Krautoload\ClassLoader_Interface $cachedLoader
           */
          $cachedLoader = new $cachedLoaderClass($loader, $options['cache_prefix']);
          // Replace the loader on the spl stack.
          $loader->unregister();
          $cachedLoader->register();
          // @todo Add the cached loader to the adapter to make it accessible to the world?
        }
    }

    self::$adapter = $adapter;
    return $adapter;
  }

  /**
   * Gets the $adapter object, if exists.
   *
   * @return \Krautoload\Adapter_ClassLoader_Interface
   * @throws Exception
   */
  static function registration() {
    if (!isset(self::$adapter)) {
      throw new Exception("Krautoload::start() must run before Krautoload::registration()");
    }
    return self::$adapter;
  }
}
