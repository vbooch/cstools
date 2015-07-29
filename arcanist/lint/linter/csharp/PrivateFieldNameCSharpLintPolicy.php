<?php

final class PrivateFieldNameCSharpLintPolicy
  extends RefactoringCSharpLintPolicy {
  
  const PRIVATE_FIELD_NAME = '1';
  
  public function getCode() {
    return 'PNAME';
  }
  
  public function getName() {
    return pht('Private Field Naming Conventions');
  }
  
  public function getDescription() {
    return pht(
      'Ensures that private field declarations and usages use '.
      'the correct naming conventions.');
  }
  
  public function getLintSeverityMap() {
    return array(
      self::PRIVATE_FIELD_NAME => 
        ArcanistLintSeverity::SEVERITY_ERROR,
    );
  }
  
  public function getLintNameMap() {
    return array(
      self::PRIVATE_FIELD_NAME =>
        pht('Private field is incorrectly named'),
    );
  }
  
  public function analyzeNode($path, array $node, array $parents) {
    if ($this->getType($node) === 'CompilationUnitSyntax') {
      $classes = $this->getClasses($path, $node);
      
      foreach ($classes as $class) {
        $private_fields = $class->getFields(CSharpVisibility::VISIBILITY_PRIVATE);
        foreach ($private_fields as $field) {
          $normalized_name = $this->normalizeName($field->getName());
          if ($normalized_name !== $field->getName()) {
            $references = idx(
              $field->getDeclaratorASTNode(),
              'References',
              array());
            foreach ($references as $reference) {
              $this->raiseLintAtLine(
                idx(idx($reference, 'Start', array()), 'Line', null),
                idx(idx($reference, 'Start', array()), 'Character', null),
                self::PRIVATE_FIELD_NAME,
                'This field is not named correctly.  A correctly '.
                'formatted name would be \''.$normalized_name.'\'',
                $field->getName(),
                $normalized_name);
            }
          }
        }
      }
    }
  }
  
  private function normalizeName($name) {
    if ($name[0] !== '_') {
      $name = '_'.$name;
    }
    
    $name = $name[0].strtolower($name[1]).substr($name, 2);
    $name = '_'.str_replace('_', '', $name);
    return $name;
  }
  
  public function analyzeToken($path, array $token, array $parents) {
  }

  
}

