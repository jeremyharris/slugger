[![build
status](https://travis-ci.org/jeremyharris/slugger.svg?branch=master)](https://travis-ci.org/jeremyharris/slugger)

# Slugger

> BREAKING CHANGE
> The original Slugger used named parameters exclusively. It now defaults to
> passed arguments. The configuration array has also changed. Please read the
> README for information on the new configuration. To use the original version,
> checkout the version 1.0 tag.

[Slugger][3] is a plugin that basically rewrites cake urls (using routing) into
slugged urls automatically:

    '/posts/view/12'

automatically becomes

    '/posts/view/my-post-title'

This avoids the need to store a slug in the db, manage it, check for duplicates,
etc. It also avoids the `Model::findBySlug()` solution that many people use.
Search for your post using the primary key instead! (Initial development sparked
by [Mark Story's blog][1]).

The slug is then transparently reverted back into the proper format for your
controller action.

## Requirements

* CakePHP 2.0.x (check tags for older versions of CakePHP)

## Installation

### Manual

* Download this: http://github.com/jeremyharris/slugger/zipball/master
* Unzip that download.
* Copy the resulting folder to app/Plugin/Slugger/

### GIT Submodule

In your app directory type:

    git submodule add git://github.com/jeremyharris/slugger.git Plugin/Slugger
    git submodule update --init

### Composer

Ensure `require` is present in `composer.json`. This will install the plugin into Plugin/Slugger:

    {
        "require": {
            "jeremyharris/slugger": "dev-master"
        }
    }

If you need the old version of slugger, make sure to replace "dev-master" with
"1.0".

## Usage

    App::uses('SluggableRoute', 'Slugger.Routing/Route');

    Router::connect(/posts/:action/:Post,
        array(),
        array(
            'routeClass' => 'Slugger.SluggableRoute',
            'models' => array('Post')
        )
    );

This is the minimal default configuration. We're using the SluggableRoute class
for this route, and checking for the Post model to generate slugs. The `:Post`
key is our passed key (`$id` in the action).

### Options

    Router::connect(/posts/:action/:Post,
        array(),
        array(
            'routeClass' => 'Slugger.SluggableRoute',
            'models' => array(
                '<MODEL_NAME>' => array(
                    'slugField' => '<FIELD_TO_SLUG>',
                    'param' => '<SLUG_PARAM>'
                )
            ),
            'slugFunction' => '<SLUG_FUNCTION>'
        )
    );

- `<MODEL_NAME>` **Required** At least one model name is required. These models
will be searched when pulling and generating the slug.
- `<FIELD_TO_SLUG>` By default, Slugger slugs the `$displayField` set on the
model. If you wish to use a different field as the slug, define it here.
- `<SLUG_PARAM>` By default, `:<MODEL_NAME>` is taken from the route and replaced
with a slug. If it is undefined, it assumes the first passed arg. You can also
configure it to use named parameters.
- `<SLUG_FUNCTION>` A callable. By default, uses `Inflector::slug`.

#### Defining the slug param

The slug parameter is what Slugger pulls from the routed URL and replaces with
your slug. By default, it checks for the `:<MODEL_NAME>` passed key.

`:Post` Slugs the `:Post` route element:

    /post/view/5 --> /post/view/my-post-title

`Post` Slugs the `Post` named param:

    /post/view/Post:5 --> /post/view/my-post-title

#### Custom slug function

You can define a custom function to use when slugging your urls by setting the
'slugFunction' key in the route options. This key accepts a php [callback][5]
and passes one argument, the string to slug. It expects a string to be returned.

For example, to use a custom function:

    function my_custom_iconv_slugger($str) {
        $str = preg_replace('/[^a-z0-9 ]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str));
        $quotedReplacement = preg_quote($replacement, '/');
        $merge = array(
            '/[^\s\p{Ll}\p{Lm}\p{Lo}\p{Lt}\p{Lu}\p{Nd}]/mu' => ' ',
            '/\\s+/' => $replacement,
            sprintf('/^[%s]+|[%s]+$/', $quotedReplacement, $quotedReplacement) => '',
        );
        return strtolower(preg_replace(array_keys($merge), array_values($merge), $str));
    }
    Router::connect('/posts/:action/*',
        array(),
        array(
            'routeClass' => 'SluggableRoute',
            'models' => array('Post'),
            'slugFunction' => 'my_custom_iconv_slugger'
        )
    );

[iconv][4] is a PHP module that encodes strings in a different character set,
thereby stripping invalid characters. It's much faster but depends on your
system's setup.

### Using Slugger in your application

Create links using Cake's helpers and Router to take advantage of automatically
generated slugs:

    array(
        'controller' => 'posts',
        'action' => 'view',
        12
    )

turns into a url string like `/posts/view/my-post-title`, then back into the
proper request for your controller to handle by putting `12` back into the passed
arguments. In your controller, get the post id by checking:

    function view($id = null) {
        $post = $this->Post->read(null, $id);
        // do controller stuff
    }

If you have defined a custom `<SLUG_PARAM>`, Slugger will replace whatever
parameter type you chose and put the original route array back together.

## Caching

Slugger caches by default. When you update records that the Sluggable route uses,
you'll need to remove the cache. For example, updating a User's username

    App::uses('SlugCache', 'Slugger.Cache');

    $this->User->id = 3;
    $this->User->saveField('username', 'newUsername');
    // invalidate user slugs
    SlugCache::invalidate('User');

To invalidate a single user you must regenerate the slug yourself and save it in
the cache:

    $this->User->id = 3;
    $newSlug = 'my-new-slug';
    $userSlugs = SlugCache::get('User');
    $userSlugs[$this->User->id] = $newSlug;
    SlugCache::set('User', $userSlugs);

Slugger uses the 'Slugger' cache config to cache, so customize that configuration
to change caching engines.

## Examples

### Passed Argument example using first arg (default bake)

    Router::connect(/posts/:action/*,
        array(),
        array(
            'routeClass' => 'Slugger.SluggableRoute',
            'models' => array('Post')
        )
    );

Using the above route

    array(
        'controller' => 'posts',
        'action' => 'view',
        12
    )

Becomes the `/posts/view/sluggable-is-cool`, and is accessed in the
controller as so:

    function view($id) {
        $post = $this->Post->read(null, $id);
        // do controller stuff
    }

### Passed Argument example using keyed passed arg

    Router::connect(/posts/:action/:post_id/*,
        array(),
        array(
            'pass' => array('post_id'),
            'routeClass' => 'Slugger.SluggableRoute',
            'models' => array(
                'Post' => array(
                    'param' => ':post_id'
                )
            )
        )
    );

Using the above route

    array(
        'controller' => 'posts',
        'action' => 'view',
        'anotherArg',
        'post_id' => 12
    )

Becomes the `/posts/view/anotherArg/sluggable-is-cool`, and is accessed in the
controller as so:

    function view($id, $anotherArgWillBeHere) {
        $post = $this->Post->read(null, $id);
        // do controller stuff
    }

#### Caveats

A couple of things to note if using keyed passed args in your routes.

- You cannot use regex to validate route elements using this method because
routes are parsed before Slugger rewrites them, and they would fail due to the
url string not matching an expected integer regex
- Missing slugs (i.e., `/posts/missing-title` where `missing-title` isn't found
as a slug) will still add the `:key` parameter to the route params because regex
validation cannot be done

### Named Parameter example

    Router::connect(/posts/:action/*,
        array(),
        array(
            'routeClass' => 'Slugger.SluggableRoute',
            'models' => array(
                'Post' => array(
                    'slugField' => 'post_title',
                    'param' => 'Post'
                ),
                'Author' => array(
                    'param' => 'Author'
                )
            )
        )
    );

Using the above route

    array(
        'controller' => 'posts',
        'action' => 'view',
        'Post' => 12,
        'Author' => 1
    )

Becomes the `/posts/view/jeremy/sluggable-is-cool`, and is accessed in the
controller as so:

    function view() {
        $post = $this->Post->read(null, $this->request->params['named']['Post']);
        $author = $this->Post->Author->read(null, $this->request->params['named']['Author']);
        // do controller stuff
    }

## Notes and Features

* More than one model can be passed via the `models` param in the route
  options.
* If a model has (what will become) duplicate slugs, sluggable route will
  automatically prepend the id to the slug so it doesn't conflict
* If no slug is found, it will fall back to the original url so you
  don't have to change anything in your database
* Don't think of this as permalinks! These are just to make your url's a little
  prettier

## Limitations

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
[5]: http://us.php.net/manual/en/language.pseudo-types.php#language.types.callback
