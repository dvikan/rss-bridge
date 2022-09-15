<?php

declare(strict_types=1);

namespace RssBridge\Tests;

use Configuration;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testValueFromDefaultConfig()
    {
        Configuration::loadConfiguration();
        $this->assertSame(null, Configuration::getConfig('foobar', ''));
        $this->assertSame(null, Configuration::getConfig('foo', 'bar'));
        $this->assertSame(null, Configuration::getConfig('cache', ''));
        $this->assertSame('UTC', Configuration::getConfig('system', 'timezone'));
    }

    public function testValueFromCustomConfig()
    {
        Configuration::loadConfiguration(['system' => ['timezone' => 'Europe/Berlin']]);
        $this->assertSame('Europe/Berlin', Configuration::getConfig('system', 'timezone'));
    }

    public function testValueFromEnv()
    {
        putenv('RSSBRIDGE_system_timezone=Europe/Berlin');
        putenv('RSSBRIDGE_TwitterV2Bridge_twitterv2apitoken=aaa');
        putenv('RSSBRIDGE_SQLiteCache_file=bbb');
        Configuration::loadConfiguration([], getenv());
        $this->assertSame('Europe/Berlin', Configuration::getConfig('system', 'timezone'));
        $this->assertSame('aaa', Configuration::getConfig('TwitterV2Bridge', 'twitterv2apitoken'));
        $this->assertSame('bbb', Configuration::getConfig('SQLiteCache', 'file'));
        $this->assertSame('bbb', Configuration::getConfig('sqlitecache', 'file'));
    }

    public function test()
    {
        Configuration::loadConfiguration([], [], '1.2.3.4', null);
        $this->assertFalse(Configuration::getConfig('system', 'debug'));
        $this->assertFalse(Configuration::getConfig('system', 'is_secure'));
    }

    public function test2()
    {
        Configuration::loadConfiguration([], [], '1.2.3.4', '');
        $this->assertTrue(Configuration::getConfig('system', 'debug'));
        $this->assertFalse(Configuration::getConfig('system', 'is_secure'));
    }

    public function test3()
    {
        Configuration::loadConfiguration([], [], '127.0.0.1', "127.0.0.1\n");
        $this->assertTrue(Configuration::getConfig('system', 'debug'));
        $this->assertTrue(Configuration::getConfig('system', 'is_secure'));
    }

    public function test4()
    {
        Configuration::loadConfiguration([], [], '1.2.3.4', "8.8.8.8\n");
        $this->assertFalse(Configuration::getConfig('system', 'debug'));
        $this->assertFalse(Configuration::getConfig('system', 'is_secure'));
    }
}
