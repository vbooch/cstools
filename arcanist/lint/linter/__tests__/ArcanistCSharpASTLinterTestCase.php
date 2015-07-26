<?php

final class ArcanistCSharpASTLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/csharpast/');
  }

}
