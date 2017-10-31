WPQuery-Model
============

Implements an Eloquent like model abstract for WPQuery

**Using**

Extend the Model as you see fit.

Make sure to set the Models directory:

```php
use Apility\WPQuery\Model;

class MyModel extends Model
{
    protected $host = 'http://localhost/';
    protected $table = 'mytable';
}
```

## Usage example

```php
use Apility\WPQuery\Model;

class Post extends Model
{
    protected $host = 'http://localhost/';
    protected $table = 'posts';
}

$posts = Post::all();
```

## Finding Entries

```php
$post = Post::find(1);
```