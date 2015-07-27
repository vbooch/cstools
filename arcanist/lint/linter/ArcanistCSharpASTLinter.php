<?php

final class ArcanistCSharpASTLinter extends ArcanistLinter {

  private $loaded = false;
  private $runtimeEngine;
  private $csastPath;
  private $futures;
  private $analyzers;
  private $globalOffset = array();
  
  private static function getAnalyzers() {
    static $analyzers = null;
    if ($analyzers === null) {
      $analyzers = id(new PhutilSymbolLoader())
        ->setAncestorClass('CSharpLintPolicy')
        ->loadObjects();
    }
    return $analyzers;
  }

  public function getLinterName() {
    return 'CS';
  }

  public function getLinterConfigurationName() {
    return 'csharp-ast';
  }
  
  public function getLintSeverityMap() {
    $severities = array();
    foreach ($this->analyzers as $analyzer) {
      foreach ($analyzer->getLintSeverityMap() as $key => $value) {
        $severities[$analyzer->getCode().$key] = $value;
      }
    }
    $names['BOM'] = ArcanistLintSeverity::SEVERITY_ERROR;
    return $severities;
  }

  public function getLintNameMap() {
    $names = array();
    foreach ($this->analyzers as $analyzer) {
      foreach ($analyzer->getLintNameMap() as $key => $value) {
        $names[$analyzer->getCode().$key] = $value;
      }
    }
    $names['BOM'] = pht('Byte-order-mark should not be present');
    return $names;
  }

  private function loadEnvironment() {
    if ($this->loaded) {
      return;
    }

    if (phutil_is_windows()) {
      $this->runtimeEngine = '';
    } else if (Filesystem::binaryExists('mono')) {
      $this->runtimeEngine = 'mono ';
    } else {
      throw new Exception(pht(
        'Unable to find Mono and you are not on Windows'));
    }

    $base_path = phutil_get_library_root('cstools');
    $this->csastPath = $base_path.'/csast/bin/Debug/csast.exe';
    $this->analyzers = self::getAnalyzers();
    $this->loaded = true;
  }

  public function willLintPaths(array $paths) {
    $this->loadEnvironment();
    
    $this->futures = array();
    foreach ($paths as $path) {
      $future = new ExecFuture(
        '%C %s',
        $this->runtimeEngine.$this->csastPath,
        $path);
      $future->setUseWindowsFileStreams(true);
      $future->setCWD($this->getProjectRoot());
      $this->futures[$path] = $future;
    }
  }
  
  public function didLintPaths(array $paths) {
    foreach (new FutureIterator($this->futures) as $path_orig => $future) {
      list($stdout, $stderr) = $future->resolvex();
      $json = phutil_json_decode($stdout);
      
      $this->setActivePath($path_orig);
      
      // Check for a byte-order-mark in the file, and strip it if
      // it exists.
      $data = $this->getData($path_orig);
      $this->globalOffset[head_key($json)] = 0;
      if (ord($data[0]) === 239 && ord($data[1]) === 187 &&
          ord($data[2]) === 191) {
        $this->raiseLintAtOffset(
          0,
          'BOM',
          'All files are stored in ASCII, so the BOM is not '.
          'permitted.',
          substr($data, 0, 3),
          '');
        
        // All future lint messages will be offset by 3.
        $this->globalOffset[head_key($json)] += 3;
      }
      
      foreach ($json as $path => $ast) {
        $this->analyzeNode($path, $ast, array());
      }
    }
  }
  
  private function analyzeNode($path, array $node, array $parents) {
    foreach ($this->analyzers as $analyzer) {
      $analyzer->setArcanistCSharpASTLinter($this);
      $analyzer->setGlobalOffset(idx($this->globalOffset, $path, 0));
      $analyzer->analyzeNode($path, $node, $parents);
    }
    
    array_push($parents, $node);
    foreach (idx($node, 'Children', array()) as $child) {
      if (idx($child, 'Type') === 'token') {
        $this->analyzeToken($path, $child, $parents);
      } elseif (idx($child, 'Type') === 'syntax') {
        $this->analyzeNode($path, $child, $parents);
      }
    }
    array_pop($parents);
  }

  private function analyzeToken($path, array $token, array $parents) {
    foreach ($this->analyzers as $analyzer) {
      $analyzer->setArcanistCSharpASTLinter($this);
      $analyzer->setGlobalOffset(idx($this->globalOffset, $path, 0));
      $analyzer->analyzeToken($path, $token, $parents);
    }
  }

}
