<?php
namespace Hashid\Test\Model\Behavior;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;
use Hashid\Model\Behavior\HashidBehavior;
use Hashids\Hashids;

class HashidBehaviorTest extends TestCase {

	/**
	 * @var array
	 */
	public $fixtures = [
		'plugin.Hashid.Addresses'
	];

	/**
	 * @var \Cake\ORM\Table;
	 */
	public $Addresses;

	/**
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Configure::write('Hashid', [
				'debug' => false,
			]
		);
		Configure::write('Security', [
				'salt' => null // For testing
			]
		);

		$this->Addresses = TableRegistry::get('Hashid.Addresses');
		$this->Addresses->addBehavior('Hashid.Hashid');
	}

	/**
	 * @return void
	 */
	public function tearDown() {
		parent::tearDown();

		unset($this->Addresses);
		TableRegistry::clear();
	}

	/**
	 * @return void
	 */
	public function testSave() {
		$data = [
			'city' => 'Foo'
		];
		$address = $this->Addresses->newEntity($data);
		$res = $this->Addresses->save($address);
		$this->assertTrue((bool)$res);

		$this->assertNull($address->hash);
	}

	/**
	 * @return void
	 */
	public function testSaveAndFind() {
		$data = [
			'city' => 'Foo'
		];
		$address = $this->Addresses->newEntity($data);
		$res = $this->Addresses->save($address);

		$id = $address->id;
		$hasher = new Hashids();
		$hashid = $hasher->encode($id);

		$address = $this->Addresses->find('hashed', [HashidBehavior::HID => $hashid])->first();
		$this->assertTrue((bool)$address);
	}

	/**
	 * @return void
	 */
	public function testSaveDebugMode() {
		$this->Addresses->behaviors()->Hashid->config('field', 'hashid');
		$this->Addresses->behaviors()->Hashid->config('debug', true);

		$data = [
			'city' => 'Foo'
		];
		$address = $this->Addresses->newEntity($data);
		$res = $this->Addresses->save($address);
		$this->assertTrue((bool)$res);

		$this->assertSame('l5-3', $address->hashid);
	}

	/**
	 * @return void
	 */
	public function testFindDebugMode() {
		Configure::write('debug', true);
		$this->Addresses->removeBehavior('Hashid');
		$this->Addresses->addBehavior('Hashid.Hashid', ['field' => 'hashid', 'debug' => null]);

		$data = [
			'city' => 'Foo'
		];
		$address = $this->Addresses->newEntity($data);
		$res = $this->Addresses->save($address);

		$id = $address->id;
		$hasher = new Hashids();
		$hashid = $hasher->encode($id) . '-' . $id;

		$address = $this->Addresses->find('hashed', [HashidBehavior::HID => $hashid])->first();
		$this->assertTrue((bool)$address);
	}

	/**
	 * @return void
	 */
	public function testSaveWithField() {
		$this->Addresses->behaviors()->Hashid->config('field', 'hash');

		$data = [
			'city' => 'Foo'
		];
		$address = $this->Addresses->newEntity($data);
		$res = $this->Addresses->save($address);
		$this->assertTrue((bool)$res);
		$this->assertSame(3, $address->id);

		$hasher = new Hashids();
		$expected = $hasher->encode($address->id);
		$this->assertEquals($expected, $address->hash);
	}

	/**
	 * @return void
	 */
	public function testFindHashedWithField() {
		$this->Addresses->behaviors()->Hashid->config('field', 'hash');

		$data = [
			'city' => 'Foo'
		];
		$address = $this->Addresses->newEntity($data);
		$res = $this->Addresses->save($address);

		$hashid = $address->hash;

		$address = $this->Addresses->find('hashed', [HashidBehavior::HID => $hashid])->first();
		$this->assertTrue((bool)$address);
	}

	/**
	 * @return void
	 */
	public function testFindWithFieldFalse() {
		$this->Addresses->behaviors()->Hashid->config('field', false);

		$address = $this->Addresses->find()->where(['city' => 'NoHashId'])->first();
		$hashid = $this->Addresses->encodeId($address->id);

		$this->Addresses->behaviors()->Hashid->config('field', 'hash');

		// Should also be included now
		$address = $this->Addresses->find()->where(['city' => 'NoHashId'])->first();
		$this->assertSame($hashid, $address->hash);

		// Should also be included now
		$address = $this->Addresses->get($address->id);
		$this->assertSame($hashid, $address->hash);
	}

	/**
	 * @return void
	 */
	public function testFindWithIdAsField() {
		$this->Addresses->behaviors()->Hashid->config('field', 'id');

		$address = $this->Addresses->find()->where(['city' => 'NoHashId'])->first();
		$hashid = $this->Addresses->encodeId($address->getOriginal('id'));
		$this->assertSame($hashid, $address->id);

		$address = $this->Addresses->patchEntity($address, ['postal_code' => '678']);

		$result = $this->Addresses->save($address);
		$this->assertTrue((bool)$result);

		$address = $this->Addresses->find()->where(['city' => 'NoHashId'])->first();
		$this->assertSame($hashid, $address->id);

		// hashid is k5
		// Only failing test
		$address = $this->Addresses->get($hashid);
		$this->assertSame($hashid, $address->id);
	}

	/**
	 * @return void
	*/
	public function testFindHashed() {
		$address = $this->Addresses->find()->where(['id' => 'jR'])->firstOrFail();
		$this->assertTrue((bool)$address);
	}

	/**
	 * @expectedException \Cake\Datasource\Exception\RecordNotFoundException
	 * @return void
	 */
	public function testFindHashedFail() {
		$this->Addresses->find()->where(['id' => 'jRx'])->firstOrFail();
	}

	/**
	 * @return void
	 */
	public function testFindFieldFalse() {
		$this->Addresses->behaviors()->Hashid->config('field', false);

		$address = $this->Addresses->find()->where(['id' => 1])->firstOrFail();
		$this->assertTrue((bool)$address);
	}

	/**
	 * @expectedException \Cake\Datasource\Exception\RecordNotFoundException
	 * @return void
	 */
	public function testFindHashedFail2() {
		$this->Addresses->find()->where(['id' => 1])->firstOrFail();
	}

	/**
	 * @return void
	 */
	public function testFindHashedWithFieldFirst() {
		$this->Addresses->behaviors()->Hashid->config('field', 'hash');
		$this->Addresses->behaviors()->Hashid->config('findFirst', true);

		$hashid = 'k5';
		$address = $this->Addresses->find('hashed', [HashidBehavior::HID => $hashid]);
		$this->assertSame(2, $address->id);
	}

	/**
	 * @return void
	 */
	public function testEncode() {
		$this->Addresses->behaviors()->Hashid->config('field', 'hid');

		$address = $this->Addresses->newEntity();
		$this->Addresses->encode($address);

		$this->assertNull($address->hid);

		$address->id = 2;
		$this->Addresses->encode($address);

		$expected = 'k5';
		$this->assertSame($expected, $address->hid);
	}

	/**
	 * @return void
	 */
	public function testEncodeSecuritySalt() {
		Configure::write('Security', [
				'salt' => '123'
		]);
		$this->Addresses->removeBehavior('Hashid');
		$this->Addresses->addBehavior('Hashid.Hashid', ['field' => 'hid', 'salt' => true]);

		$address = $this->Addresses->newEntity();

		$address->id = 2;
		$this->Addresses->encode($address);

		$expected = 'ng';
		$this->assertSame($expected, $address->hid);
	}

}