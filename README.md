Scraper - PHP package for webpage scraping
=============================================
This package uses [Goutte](https://github.com/FriendsOfPHP/Goutte) to crawl webpage and extract data (favicon, title, description feature image etc) from url.

Installation
---------------

Add ``manishgs/scraper`` as a require dependency in your ``composer.json`` file:

``composer require manishgs/scraper:^0.1.0``

Usage
-------
```php
    use Scraper\Grab;
    // create new Grab instance
    $grab = new Grab();
    // set Url    
    $grab = $grab->setUrl('http://google.com');
    // extract data
    $article = $grab->getInfo();
    print_r($article);
```
