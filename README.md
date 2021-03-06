# phergie/phergie-irc-plugin-react-nickserv

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin for interacting with the NickServ agent to authenticate the bot's identity.

[![Build Status](https://secure.travis-ci.org/phergie/phergie-irc-plugin-react-nickserv.png?branch=master)](http://travis-ci.org/phergie/phergie-irc-plugin-react-nickserv)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "phergie/phergie-irc-plugin-react-nickserv": "dev-master"
    }
}
```

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Configuration

```php
new \Phergie\Irc\Plugin\React\NickServ\Plugin(array(

    // Required: password used to authenticate with NickServ
    'password' => 'YOUR-NICKSERV-PASSWORD-HERE',

))
```

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.
