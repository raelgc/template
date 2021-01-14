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
		$tpl->FOO = 'baz';
		$tpl->block("BLOCK_SIMPLE");
		$this->assertEquals("Text with VAR: bar\nText with VAR: baz", trim($tpl->parse()));
	}

	public function testNestedBlocks()
	{
		$tpl = new Template(__DIR__ . '/nested_block.html');
		$tpl->OUT_TOP_VAR = 'foo';
		$tpl->OUT_BOTTOM_VAR = 'bar';
		$tpl->INNER_VAR = 'baz';
		$tpl->block("BLOCK_INNER");
		$tpl->INNER_VAR = 'qux';
		$tpl->block("BLOCK_INNER");
		$this->assertEquals("Out top content with foo\nInner content with baz-\nInner content with qux-\nOut bottom content with bar", trim($tpl->parse()));
	}

	public function testUnparsedFinallyBlock()
	{
		$tpl = new Template(__DIR__ . '/finally_block.html');
		$tpl->FOO = 'bar';
		$tpl->block("BLOCK_SIMPLE");
		$this->assertEquals('Text with VAR: bar', trim($tpl->parse()));
	}


	public function testFinallyBlock()
	{
		$tpl = new Template(__DIR__ . '/finally_block.html');
		$this->assertEquals('Content in finally block', trim($tpl->parse()));
	}

	public function testClearVarBlock()
	{
		$tpl = new Template(__DIR__ . '/clear_var_in_block.html');
		$tpl->VALUE = 'foo';
		$tpl->SELECTED = 'selected';
		$tpl->block('BLOCK_OPTION');
		$tpl->clear('SELECTED');
		$tpl->block('BLOCK_OPTION');
		$this->assertEquals("Test if foo is selected\nTest if foo is", trim($tpl->parse()));
	}

	public function testMultipleFiles()
	{
		$tpl = new Template(__DIR__ . '/multiple_files_parent.html');
		$tpl->addFile('CONTENT', __DIR__ . '/multiple_files_child.html');
		$tpl->VAR = 'foo';
		$this->assertEquals("<div><p>foo</p>\n</div>", trim($tpl->parse()));
	}

	public function testSimpleObject()
	{
		$tpl = new Template(__DIR__ . '/simple_object.html');
		$foo = new stdClass;
		$foo->bar = 'foobar';
		$tpl->FOO = $foo;
		$this->assertEquals('foobar', trim($tpl->parse()));
	}

	public function testObjectWithGetter()
	{
		$tpl = new Template(__DIR__ . '/simple_object.html');
		$foo = new class {
			private $bar;
			public function setBar($bar) {
				$this->bar = $bar;
			}
			public function getBar() { 
				return $this->bar;
			}
		};
		$foo->setBar('foobar');
		$tpl->FOO = $foo;
		$this->assertEquals('foobar', trim($tpl->parse()));
	}

	public function testObjectWithMagicGet()
	{
		$tpl = new Template(__DIR__ . '/simple_object.html');
		$foo = new class {
			private $bar;
			public function setBar($bar) {
				$this->bar = $bar;
			}
			public function __get($var) { 
				return $this->bar;
			}
		};
		$foo->setBar('foobar');
		$tpl->FOO = $foo;
		$this->assertEquals('foobar', trim($tpl->parse()));
	}

	public function testObjectWithToString()
	{
		$tpl = new Template(__DIR__ . '/simple_var.html');
		$foo = new class {
			public function setBar($bar) {
				$this->bar = $bar;
			}
			public function __toString() { 
				return $this->bar;
			}
		};
		$foo->setBar('foobar');
		$tpl->FOO = $foo;
		$this->assertEquals('foobar', trim($tpl->parse()));
	}

	public function testArrayParse()
	{
		$tpl = new Template(__DIR__ . '/simple_var.html');
		$foo = ['bar', 'baz', 'qux'];
		$tpl->FOO = $foo;
		$this->assertEquals('bar, baz, qux', trim($tpl->parse()));
	}

	public function testObjectParse()
	{
		$tpl = new Template(__DIR__ . '/simple_var.html');
		$foo = new class {
			public function setBar($bar) {
				$this->bar = $bar;
			}
		};
		$foo->setBar('foobar');
		$tpl->FOO = $foo;
		$this->assertEquals('Object: {"bar":"foobar"}', trim($tpl->parse()));
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

	public function testComments()
	{
		$tpl = new Template(__DIR__ . '/comments.html');
		$tpl->OUT_TOP_VAR = 'foo';
		$tpl->OUT_BOTTOM_VAR = 'bar';
		$tpl->INNER_VAR = 'baz';
		$tpl->block("BLOCK_INNER");
		$tpl->INNER_VAR = 'qux';
		$tpl->block("BLOCK_INNER");
		$this->assertEquals("Out top content with foo\nInner content with baz-\nInner content with qux-\nOut bottom content with bar", trim($tpl->parse()));
	}

	public function testEscapeVar()
	{
		$tpl = new Template(__DIR__ . '/escape_var.html');
		$this->assertEquals("This is an escaped var:\n{FOO}", trim($tpl->parse()));
	}

}
