<?php declare(strict_types=1);
require_once('raelgc/view/Template.php');
use PHPUnit\Framework\TestCase;
use raelgc\view\Template;

final class TemplateTest extends TestCase {

	public function testSimpleVar(): void {
		$tpl = new Template(__DIR__.'/simple_var.html' );
		$tpl->FOO = 'bar';
		$this->assertEquals(trim($tpl->parse()), 'bar');
	}

	public function testSimpleObject(): void {
		$tpl = new Template(__DIR__.'/simple_object.html' );
		$foo = new stdClass;
		$foo->bar = 'foobar';
		$tpl->FOO = $foo;
		$this->assertEquals(trim($tpl->parse()), "foobar");
	}
}

