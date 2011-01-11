# Slugger

[Slugger][3] is a plugin that basically rewrites cake urls (using routing) into
slugged urls automatically using a named parameter.

    '/posts/view/Post:12'

automatically becomes

    '/posts/view/my-post-title'

This avoids the need to store a slug in the db, manage it, check for duplicates,
etc. It also avoids the `Model::findBySlug()` solution that many people use.
Search for your post using the primary key instead! (Initial development sparked
by [Mark Story's blog][1]).

## Usage

    App::import('Lib', array('Slugger.routes/SluggableRoute'));

    Router::connect(/posts/:action/*,
        array(),
        array(
            'routeClass' => 'SluggableRoute',
            'models' => array('Post')
        )
    );

In order for it to work, the named parameter needs to be named the model's name
and the value needs to be the primaryKey value. Passing a cake url array such as

    array(
        'controller' => 'posts',
        'action' => 'view',
        'Post' => 12
    )

turns into a url string like `/posts/view/my-post-title`, then back into the
proper request for your controller to handle by putting `'Post' => 12` back
into the named parameters. In your controller, get the post id by checking
`passedArgs`.

    function view() {
        $id = $this->passedArgs['Post'];
        $post = $this->Post->read(null, $id);
        // do controller stuff
    }

By default, the field used for the slug is the model's `displayField`. To change
this, change your connection to:

    Router::connect('/posts/:action/*',
        array(),
        array(
            'routeClass' => 'SluggableRoute',
            'models' => array('Post' => 'different_field')
        )
    );

Also in the options is the option to set whether or not to use `iconv()`. [iconv][4]
is a PHP module that encodes strings in a different character set, thereby
stripping invalid characters. While it's much faster (see benchmark test),
depending on your setup and locale it may not slug as expected.

    Router::connect('/posts/:action/*',
        array(),
        array(
            'routeClass' => 'SluggableRoute',
            'models' => array('Post'),
            'iconv' => true
        )
    );

## Caching

Slugger caches by default. When you update records that the Sluggable route uses,
you'll need to remove the cache. For example, updating a User's username

    $this->User->id = 3;
    $this->User->saveField('username', 'newUsername');
    $Route = new SluggableRoute('/', array(), array('models' => array('User')));
    $success = $Route->invalidateCache('User', $this->User->id);

Invalidating after saves and deletions is a good idea. You can also remove all
of the cache for an entire model like so:

    $Route = new SluggableRoute('/', array(), array('models' => array('User')));
    $success = $Route->invalidateCache('User');

## Notes and Features

* More than one model can be passed via the `models` param in the route
  options.
* If a model has (what will become) duplicate slugs, sluggable route will
  automatically prepend the id to the slug so it doesn't conflict
* If no slug is found, it will fall back to the original `Post:12` url so you
  don't have to change anything in your database
* Don't think of this as permalinks! These are just to make your url's a little
  prettier

## Limitations

* Hasn't been tested with large amounts of data (use at your own risk to
  preformance!), but it is cached so hits on the db should be minimal. The cache
  config is `Slugger.short` if you need to clear it. The biggest bottleneck, if
  any, would be in writing a ton of urls on the same page. I'm testing ways to
  improve this.
* Can conflict with multiple models with the same slug. A solution would be
  not to slug more than one model per route
* If someone was to bookmark a slugged url and after the fact you added a post
  with the same name, the bookmarked url would no longer work because the id
  would be prepended to it. In order to avoid this from ever happening, pass
  `'prependPk' => true` to the route options and the id will always be prepended
  to the slug

## License

Licensed under The MIT License
[http://www.opensource.org/licenses/mit-license.php][2]
Redistributions of files must retain the above copyright notice.

[1]: http://mark-story.com/posts/view/using-custom-route-classes-in-cakephp
[2]: http://www.opensource.org/licenses/mit-license.php
[3]: http://42pixels.com/blog/slugs-ugly-bugs-pretty-urls
[4]: http://us.php.net/manual/en/function.iconv.php

## Authors

* Pierre Martin (real34) - Cache invalidation