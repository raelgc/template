<?php
require_once('raelgc/view/Template.php');

use PHPUnit\Framework\TestCase;
use raelgc\view\Template;

final class TemplateTest extends TestCase
{

	public function testSimpleVar()
	{
		$tpl = new Template(__DIR__ . '/simple_var.html');
		$tpl->FOO = 'bar';
		$this->assertEquals('bar', trim($tpl->parse()));
	}

	public function testIfVarExists()
	{
		$tpl = new Template(__DIR__ . '/simple_var.html');
		if ($tpl->exists('FOO')) {
			$tpl->FOO = 'bar';
		}
		$this->assertEquals('bar', trim($tpl->parse()));
	}

	public function testIfVarDoNotExists()
	{
		$tpl = new Template(__DIR__ . '/simple_var.html');
		// variables are casesensitive
		if ($tpl->exists('foo')) {
			$tpl->foo = 'bar';
		}
		$this->assertEquals('', trim($tpl->parse()));
	}

	public function testModifiers() {
		$tpl = new Template(__DIR__ . '/modifier.html');
		$tpl->foo = 'Bar';
		$this->assertEquals('Baz', trim($tpl->parse()));
	}

	public function testUnparsedBlock()
	{
		$tpl = new Template(__DIR__ . '/simple_block.html');
		$tpl->FOO = 'bar';
		$this->assertEquals('', trim($tpl->parse()));
	}

	public function testSimpleBlock()
	{
		$tpl = new Template(__DIR__ . '/simple_block.html');
		$tpl->FOO = 'bar';
		$tpl->block("BLOCK_SIMPLE");
		$this->assertEquals('Text with VAR: bar', trim($tpl->parse()));
	}

	public function testLoopBlock()
	{
		$tpl = new Template(__DIR__ . '/simple_block.html');
		$tpl->FOO = 'bar';
		$tpl->block("BLOCK_SIMPLE");
		$tpl->FOO = 'baz';
		$tpl->block("BLOCK_SIMPLE");
		$this->assertEquals("Text with VAR: bar\nText with VAR: baz", trim($tpl->parse()));
	}

	public function testSimpleObject()
	{
		$tpl = new Template(__DIR__ . '/simple_object.html');
		$foo = new stdClass;
		$foo->bar = 'foobar';
		$tpl->FOO = $foo;
		$this->assertEquals('foobar', trim($tpl->parse()));
	}

	public function testFailureSimpleObjectCaseMismatch()
	{
		$this->expectException(RuntimeException::class);
		$tpl = new Template(__DIR__ . '/simple_object.html');
		$foo = new stdClass;
		$foo->bar = 'foobar';
		// foo is set lowercase but template calls FOO (uppercase)
		$tpl->foo = $foo;
		$tpl->parse();
	}
}
