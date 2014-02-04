<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2013, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\storage\cache\adapter;

use Exception;
use Redis as RedisCore;
use lithium\storage\Cache;
use lithium\storage\cache\adapter\Redis;

class RedisTest extends \lithium\test\Unit {

	public $redis;

	protected $_redis;

	public function __construct(array $config = array()) {
		$defaults = array(
			'host' => '127.0.0.1',
			'port' => 6379
		);
		parent::__construct($config + $defaults);
	}

	/**
	 * Skip the test if the Redis extension is unavailable.
	 *
	 * @return void
	 */
	public function skip() {
		$this->skipIf(!Redis::enabled(), 'The redis extension is not installed.');

		$redis = new RedisCore();
		$cfg = $this->_config;

		try {
			$redis->connect($cfg['host'], $cfg['port']);
		} catch (Exception $e) {
			$info = $redis->info();
			$msg = "redis-server does not appear to be running on {$cfg['host']}:{$cfg['port']}";
			$this->skipIf(!$info, $msg);
		}
		unset($redis);
	}

	public function setUp() {
		$this->_redis = new RedisCore();
		$this->_redis->connect($this->_config['host'], $this->_config['port']);
		$this->redis = new Redis();
	}

	public function tearDown() {
		$this->_redis->flushdb();
	}

	public function testEnabled() {
		$redis = $this->redis;
		$this->assertTrue($redis::enabled());
	}

	public function testInit() {
		$redis = new Redis();
		$this->assertTrue($redis->connection instanceof RedisCore);
	}

	public function testSimpleWrite() {
		$key = 'key';
		$data = 'value';
		$keys = array($key => $data);
		$expiry = '+5 seconds';
		$time = strtotime($expiry);

		$closure = $this->redis->write($keys, $expiry);
		$this->assertInternalType('callable', $closure);

		$params = compact('keys', 'expiry');
		$result = $closure($this->redis, $params, null);
		$expected = $keys;
		$this->assertEqual($expected, $result);

		$expected = $data;
		$result = $this->_redis->get($key);
		$this->assertEqual($expected, $result);

		$result = $this->_redis->ttl($key);
		$this->assertTrue($result == 5 || $result == 4);

		$result = $this->_redis->delete($key);
		$this->assertEqual(1, $result);

		$key = 'another_key';
		$data = 'more_data';
		$keys = array($key => $data);
		$expiry = '+1 minute';
		$time = strtotime($expiry);

		$closure = $this->redis->write($keys, $expiry);
		$this->assertInternalType('callable', $closure);

		$params = compact('keys', 'expiry');
		$result = $closure($this->redis, $params, null);
		$expected = $keys;
		$this->assertEqual($expected, $result);

		$expected = $data;
		$result = $this->_redis->get($key);
		$this->assertEqual($expected, $result);

		$result = $this->_redis->ttl($key);
		$this->assertTrue($result == 60 || $result == 59);

		$result = $this->_redis->delete($key);
		$this->assertEqual(1, $result);
	}

	public function testWriteExpiryDefault() {
		$redis = new Redis(array('expiry' => '+5 seconds'));
		$key = 'default_key';
		$data = 'value';
		$keys = array($key => $data);
		$time = strtotime('+5 seconds');

		$closure = $redis->write($keys);
		$this->assertInternalType('callable', $closure);

		$expected = $data;
		$params = compact('keys');
		$result = $closure($redis, $params);
		$this->assertEqual($expected, $result);

		$result = $this->_redis->get($key);
		$this->assertEqual($expected, $result);

		$result = $this->_redis->ttl($key);
		$this->assertTrue($result == 5 || $result == 4);

		$result = $this->_redis->delete($key);
		$this->assertEqual(1, $result);
	}

	public function testWriteNoExpiry() {
		$key = 'default_key';
		$data = 'value';
		$keys = array($key => $data);

		$redis = new Redis(array('expiry' => null));
		$expiry = null;

		$closure = $redis->write($keys, $expiry);
		$result = $closure($redis, compact('keys', 'expiry'));
		$this->assertTrue($result);

		$result = $this->_redis->exists($key);
		$this->assertTrue($result);

		$expected = -1;
		$result = $this->_redis->ttl($key);
		$this->assertEqual($expected, $result);

		$this->_redis->delete($key);

		$redis = new Redis(array('expiry' => Cache::PERSIST));
		$expiry = Cache::PERSIST;

		$closure = $redis->write($keys, $expiry);
		$result = $closure($redis, compact('keys', 'expiry'));
		$this->assertTrue($result);

		$result = $this->_redis->exists($key);
		$this->assertTrue($result);

		$expected = -1;
		$result = $this->_redis->ttl($key);
		$this->assertEqual($expected, $result);

		$this->_redis->delete($key);

		$redis = new Redis();
		$expiry = Cache::PERSIST;

		$closure = $redis->write($keys, $expiry);
		$result = $closure($redis, compact('keys', 'expiry'));
		$this->assertTrue($result);

		$result = $this->_redis->exists($key);
		$this->assertTrue($result);

		$expected = -1;
		$result = $this->_redis->ttl($key);
		$this->assertEqual($expected, $result);

		$this->_redis->delete($key);
	}

	public function testWriteExpiryExpires() {
		$keys = array('key1' => 'data1');
		$expiry = '+5 seconds';
		$closure = $this->redis->write($keys, $expiry);
		$closure($this->redis, compact('keys', 'expiry'));

		$result = $this->_redis->exists('key1');
		$this->assertTrue($result);

		$this->_redis->delete('key1');

		$keys = array('key1' => 'data1');
		$expiry = '+1 second';
		$closure = $this->redis->write($keys, $expiry);
		$closure($this->redis, compact('keys', 'expiry'));

		sleep(2);

		$result = $this->_redis->exists('key1');
		$this->assertFalse($result);
	}

	public function testWriteExpiryTtl() {
		$keys = array('key1' => 'data1');
		$expiry = 5;
		$closure = $this->redis->write($keys, $expiry);
		$closure($this->redis, compact('keys', 'expiry'));

		$result = $this->_redis->exists('key1');
		$this->assertTrue($result);

		$this->_redis->delete('key1');

		$keys = array('key1' => 'data1');
		$expiry = 1;
		$closure = $this->redis->write($keys, $expiry);
		$closure($this->redis, compact('keys', 'expiry'));

		sleep(2);

		$result = $this->_redis->exists('key1');
		$this->assertFalse($result);
	}

	public function testWriteWithScope() {
		$adapter = new Redis(array('scope' => 'primary'));

		$keys = array('key1' => 'test1');
		$expiry = '+1 minute';
		$result = $adapter->write($keys, $expiry);
		$result($adapter, compact('keys', 'expiry'));

		$expected = 'test1';
		$result = $this->_redis->get('primary:key1');
		$this->assertEqual($expected, $result);

		$result = $this->_redis->get('key1');
		$this->assertFalse($result);
	}

	public function testSimpleRead() {
		$key = 'read_key';
		$data = 'read data';
		$keys = array($key);

		$result = $this->_redis->set($key, $data);
		$this->assertTrue($result);

		$closure = $this->redis->read($keys);
		$this->assertInternalType('callable', $closure);

		$expected = array($key => $data);
		$params = compact('keys');
		$result = $closure($this->redis, $params);
		$this->assertEqual($expected, $result);

		$result = $this->_redis->delete($key);
		$this->assertEqual(1, $result);

		$key = 'another_read_key';
		$data = 'read data';
		$keys = array($key);
		$time = strtotime('+1 minute');
		$expiry = $time - time();

		$result = $this->_redis->set($key, $data, $expiry);
		$this->assertTrue($result);

		$result = $this->_redis->ttl($key);
		$this->assertTrue($result == $expiry || $result == $expiry - 1);

		$closure = $this->redis->read($keys);
		$this->assertInternalType('callable', $closure);

		$expected = array($key => $data);
		$params = compact('keys');
		$result = $closure($this->redis, $params, null);
		$this->assertEqual($expected, $result);

		$result = $this->_redis->delete($key);
		$this->assertEqual(1, $result);
	}

	public function testMultiRead() {
		$data = array('key1' => 'value1', 'key2' => 'value2');
		$result = $this->_redis->mset($data);
		$this->assertTrue($result);

		$closure = $this->redis->read(array_keys($data));
		$this->assertInternalType('callable', $closure);

		$params = array('keys' => array_keys($data));
		$result = $closure($this->redis, $params, null);
		$expected = $data;
		$this->assertEqual($expected, $result);

		foreach ($data as $k => $v) {
			$result = $this->_redis->delete($k);
			$this->assertEqual(1, $result);
		}
	}

	public function testMultiWrite() {
		$keys = array('key1' => 'value1', 'key2' => 'value2');
		$expiry = '+5 seconds';
		$time = strtotime($expiry);

		$closure = $this->redis->write($keys, $expiry);
		$this->assertInternalType('callable', $closure);

		$params = array('keys' => $keys, 'expiry' => null);
		$result = $closure($this->redis, $params, null);
		$this->assertTrue($result);

		$result = $this->_redis->getMultiple(array_keys($keys));
		$expected = array_values($keys);
		$this->assertEqual($expected, $result);
	}

	public function testReadKeyThatDoesNotExist() {
		$key = 'does_not_exist';
		$keys = array($key);
		$closure = $this->redis->read($keys);

		$expected = array();
		$result = $closure($this->redis, compact('keys'));
		$this->assertIdentical($expected, $result);
	}

	public function testWriteAndReadNull() {
		$expiry = '+1 minute';
		$keys = array(
			'key1' => null
		);
		$result = $this->redis->write($keys);
		$this->assertTrue($result($this->redis, compact('keys', 'expiry')));

		$expected = $keys;
		$result = $this->redis->read(array_keys($keys));
		$this->assertEqual($expected, $result($this->redis, array('keys' => array_keys($keys))));
	}

	public function testWriteAndReadNullMulti() {
		$expiry = '+1 minute';
		$keys = array(
			'key1' => null,
			'key2' => 'data2'
		);
		$result = $this->redis->write($keys);
		$this->assertTrue($result($this->redis, compact('keys', 'expiry')));

		$expected = $keys;
		$result = $this->redis->read(array_keys($keys));
		$this->assertEqual($expected, $a = $result($this->redis, array('keys' => array_keys($keys))));

		$keys = array(
			'key1' => '',
			'key2' => 'data2'
		);
		$result = $this->redis->write($keys);
		$this->assertTrue($result($this->redis, compact('keys', 'expiry')));
	}

	public function testReadWithScope() {
		$adapter = new Redis(array('scope' => 'primary'));

		$this->_redis->set('primary:key1', 'test1', 60);
		$this->_redis->set('key1', 'test2', 60);

		$keys = array('key1');
		$expected = array('key1' => 'test1');
		$result = $adapter->read($keys);
		$result = $result($adapter, compact('keys'));
		$this->assertEqual($expected, $result);
	}

	public function testDelete() {
		$key = 'delete_key';
		$data = 'data to delete';
		$keys = array($key);
		$time = strtotime('+1 minute');

		$result = $this->_redis->set($key, $data);
		$this->assertTrue($result);

		$closure = $this->redis->delete($keys);
		$this->assertInternalType('callable', $closure);

		$params = compact('keys');
		$result = $closure($this->redis, $params);
		$this->assertTrue($result);

		$this->assertEqual(0, $this->_redis->delete($key));
	}

	public function testDeleteNonExistentKey() {
		$key = 'delete_key';
		$keys = array($key);
		$closure = $this->redis->delete($keys);
		$this->assertInternalType('callable', $closure);

		$params = compact('keys');
		$result = $closure($this->redis, $params, null);
		$this->assertFalse($result);
	}

	public function testDeleteWithScope() {
		$adapter = new Redis(array('scope' => 'primary'));

		$this->_redis->set('primary:key1', 'test1', 60);
		$this->_redis->set('key1', 'test2', 60);

		$keys = array('key1');
		$expected = array('key1' => 'test1');
		$result = $adapter->delete($keys);
		$result($adapter, compact('keys'));

		$result = (boolean) $this->_redis->get('key1');
		$this->assertTrue($result);

		$result = $this->_redis->get('primary:key1');
		$this->assertFalse($result);
	}

	public function testWriteReadAndDeleteRoundtrip() {
		$key = 'write_read_key';
		$data = 'write/read value';
		$keys = array($key => $data);
		$expiry = '+5 seconds';
		$time = strtotime($expiry);

		$closure = $this->redis->write($keys, $expiry);
		$this->assertInternalType('callable', $closure);

		$params = compact('keys', 'expiry');
		$result = $closure($this->redis, $params);
		$expected = $keys;
		$this->assertEqual($expected, $result);

		$expected = $data;
		$result = $this->_redis->get($key);
		$this->assertEqual($expected, $result);

		$closure = $this->redis->read(array_keys($keys));
		$this->assertInternalType('callable', $closure);

		$params = array('keys' => array_keys($keys));
		$result = $closure($this->redis, $params);
		$expected = $keys;
		$this->assertEqual($expected, $result);

		$closure = $this->redis->delete(array_keys($keys));
		$this->assertInternalType('callable', $closure);

		$params = array('keys' => array_keys($keys));
		$result = $closure($this->redis, $params);
		$this->assertTrue($result);

		$this->assertFalse($this->_redis->get($key));
	}

	public function testClear() {
		$result = $this->_redis->set('key', 'value');
		$this->assertTrue($result);

		$result = $this->_redis->set('another_key', 'value');
		$this->assertTrue($result);

		$result = $this->redis->clear();
		$this->assertTrue($result);

		$this->assertFalse($this->_redis->get('key'));
		$this->assertFalse($this->_redis->get('another_key'));
	}

	public function testDecrement() {
		$key = 'decrement';
		$value = 10;

		$result = $this->_redis->set($key, $value);
		$this->assertTrue($result);

		$closure = $this->redis->decrement($key);
		$this->assertInternalType('callable', $closure);

		$params = compact('key');
		$result = $closure($this->redis, $params, null);
		$this->assertEqual($value - 1, $result);

		$result = $this->_redis->get($key);
		$this->assertEqual($value - 1, $result);

		$result = $this->_redis->delete($key);
		$this->assertEqual(1, $result);
	}

	public function testDecrementNonIntegerValue() {
		$key = 'non_integer';
		$value = 'no';

		$result = $this->_redis->set($key, $value);
		$this->assertTrue($result);

		$closure = $this->redis->decrement($key);
		$this->assertInternalType('callable', $closure);

		$params = compact('key');
		$result = $closure($this->redis, $params, null);
		$this->assertFalse($result);

		$result = $this->_redis->get($key);
		$this->assertEqual($value, $result);

		$result = $this->_redis->delete($key);
		$this->assertEqual(1, $result);
	}

	public function testDecrementWithScope() {
		$adapter = new Redis(array('scope' => 'primary'));

		$this->_redis->set('primary:key1', 1, 60);
		$this->_redis->set('key1', 1, 60);

		$result = $adapter->decrement('key1');
		$result($adapter, array('key' => 'key1'));

		$expected = 1;
		$result = $this->_redis->get('key1');
		$this->assertEqual($expected, $result);

		$expected = 0;
		$result = $this->_redis->get('primary:key1');
		$this->assertEqual($expected, $result);
	}

	public function testIncrement() {
		$key = 'increment';
		$value = 10;

		$result = $this->_redis->set($key, $value);
		$this->assertTrue($result);

		$closure = $this->redis->increment($key);
		$this->assertInternalType('callable', $closure);

		$params = compact('key');
		$result = $closure($this->redis, $params, null);
		$this->assertEqual($value + 1, $result);

		$result = $this->_redis->get($key);
		$this->assertEqual($value + 1, $result);

		$result = $this->_redis->delete($key);
		$this->assertEqual(1, $result);
	}

	public function testIncrementNonIntegerValue() {
		$key = 'non_integer_increment';
		$value = 'yes';

		$result = $this->_redis->set($key, $value);
		$this->assertTrue($result);

		$closure = $this->redis->increment($key);
		$this->assertInternalType('callable', $closure);

		$params = compact('key');
		$result = $closure($this->redis, $params, null);
		$this->assertFalse($result);

		$result = $this->_redis->get($key);
		$this->assertEqual($value, $result);

		$result = $this->_redis->delete($key);
		$this->assertEqual(1, $result);
	}

	public function testIncrementWithScope() {
		$adapter = new Redis(array('scope' => 'primary'));

		$this->_redis->set('primary:key1', 1, 60);
		$this->_redis->set('key1', 1, 60);

		$result = $adapter->increment('key1');
		$result($adapter, array('key' => 'key1'));

		$expected = 1;
		$result = $this->_redis->get('key1');
		$this->assertEqual($expected, $result);

		$expected = 2;
		$result = $this->_redis->get('primary:key1');
		$this->assertEqual($expected, $result);
	}

	public function testMethodDispatch() {
		$this->_redis->flushdb();
		$this->_redis->set('some_key', 'somevalue');

		$result = $this->redis->keys('*');
		$this->assertEqual($result, array('some_key'), 'redis method dispatch failed');

		$result = $this->redis->info();
		$this->assertInternalType('array', $result, 'redis method dispatch failed');
	}

	public function testRespondsTo() {
		$this->assertTrue($this->redis->respondsTo('bgsave'));
		$this->assertTrue($this->redis->respondsTo('dbSize'));
		$this->assertFalse($this->redis->respondsTo('foobarbaz'));
	}

	public function testRespondsToParentCall() {
		$this->assertTrue($this->redis->respondsTo('applyFilter'));
		$this->assertFalse($this->redis->respondsTo('fooBarBaz'));
	}
}

?>