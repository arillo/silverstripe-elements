<?php

// use SilverStripe\Dev\SapphireTest;

/**
 * @package siteconfig
 * @subpackage tests
 */
class ElementsExtensionTest extends FunctionalTest {

	protected static $fixture_file = 'ElementsExtensionTest.yml';

	public function testGetRelations(){

		$page = $this->objFromFixture('HomePage', 'home');
		$relations = $page->getRelations();
		Debug::dump($relations);

		// $this->assertNotNull($relations, "Relations returned");
	}
}
