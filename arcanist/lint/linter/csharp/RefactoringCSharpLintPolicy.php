<?php

abstract class RefactoringCSharpLintPolicy extends CSharpLintPolicy {

  private static $classAndMethodCache = array();

  private function loadClassesAndMethods($path, $ast) {
    if (idx(self::$classAndMethodCache, $path) !== null) {
      return;
    }
    
    self::$classAndMethodCache[$path] = $this->loadClasses($ast);
  }
  
  protected function getClasses($path, $ast) {
    $this->loadClassesAndMethods($path, $ast);
    
    return self::$classAndMethodCache[$path];
  }
  
  private function loadClasses($ast) {
    $class_nodes = $this->findChildrenWithType($ast, 'ClassDeclarationSyntax');
    $struct_nodes = $this->findChildrenWithType($ast, 'StructDeclarationSyntax');
    $all_nodes = $class_nodes + $struct_nodes;
    $classes = array();
    
    foreach ($all_nodes as $node) {
      $classes[] = $this->loadClass($node);
    }
    
    return $classes;
  }
  
  private function loadClass($node) {
    $class = new CSharpClass();
    $class->setASTNode($node);
    
    $modifiers = idx($node, 'Modifiers', array());
    foreach ($modifiers as $modifier) {
      switch ($modifier) {
        case 'partial':
          $class->setIsPartial(true);
          break;
      }
    }
    
    $class->setVisibility($this->getVisibilityOfTopLevel($node));
    
    $type = CSharpClass::TYPE_CLASS;
    if ($this->getType($node) === 'StructDeclarationSyntax') {
      $type = CSharpClass::TYPE_STRUCT;
    }
    $class->setType($type);
    
    $class->setName(idx(
      idx($node, 'Identifier', array()),
      'TrimmedText',
      null));
    
    $fields = array();
    $properties = array();
    $methods = array();
    $nested_classes = array();
    foreach (idx($node, 'Members', array()) as $member) {
      switch ($this->getType($member)) {
        case 'ClassDeclarationSyntax':
        case 'StructDeclarationSyntax':
          $nested_classes[] = $this->loadClass($member);
          break;
        case 'MethodDeclarationSyntax':
          $methods[] = $this->loadMethod($member);
          break;
        case 'PropertyDeclarationSyntax':
          $properties[] = $this->loadProperty($member);
          break;
        case 'FieldDeclarationSyntax':
          foreach ($this->getChildren($member) as $declaration) {
            if ($this->getType($declaration) === 'VariableDeclarationSyntax') {
              if ($this->isNode($declaration)) {
                foreach ($this->getChildren($declaration) as $declarator) {
                  if ($this->getType($declarator) === 'VariableDeclaratorSyntax') {
                    $fields[] = $this->loadField($member, $declaration, $declarator);
                  }
                }
              }
            }
          }
          break;
      }
    }
    
    $class
      ->setFields($fields)
      ->setProperties($properties)
      ->setMethods($methods)
      ->setNestedClasses($nested_classes);
      
    return $class;
  }
  
  private function getVisibilityOfTopLevel($node) {
    $modifiers = idx($node, 'Modifiers', array());
    $visibility = CSharpVisibility::VISIBILITY_INTERNAL;
    foreach ($modifiers as $modifier) {
      switch ($modifier) {
        case 'public':
          $visibility = CSharpVisibility::VISIBILITY_PUBLIC;
          break;
        case 'internal':
          $visibility = CSharpVisibility::VISIBILITY_INTERNAL;
          break;
      }
    }
    return $visibility;
  }
  
  private function getVisibilityOfMember($node) {
    $modifiers = idx($node, 'Modifiers', array());
    $visibility = CSharpVisibility::VISIBILITY_PRIVATE;
    foreach ($modifiers as $modifier) {
      switch ($modifier) {
        case 'public':
          $visibility = CSharpVisibility::VISIBILITY_PUBLIC;
          break;
        case 'internal':
          if ($visibility === CSharpVisibility::VISIBILITY_PROTECTED) {
            $visibility = CSharpVisibility::VISIBILITY_PROTECTED_INTERNAL;
          } else {
            $visibility = CSharpVisibility::VISIBILITY_INTERNAL;
          }
          break;
        case 'protected':
          $visibility = CSharpVisibility::VISIBILITY_PROTECTED;
          break;
        case 'private':
          $visibility = CSharpVisibility::VISIBILITY_PRIVATE;
          break;
      }
    }
    return $visibility;
  }
  
  private function loadMethod($node) {
    $method = new CSharpMethod();
    $method->setASTNode($node);
    
    $method->setName(idx(
      idx($node, 'Identifier', array()),
      'TrimmedText',
      null));
      
    $method->setReturnType(idx(
      idx($node, 'ReturnType', array()),
      'TrimmedText',
      null));
    
    $method->setVisibility($this->getVisibilityOfMember($node));
      
    return $method;
  }
  
  private function loadProperty($node) {
    $property = new CSharpProperty();
    $property->setASTNode($node);
    
    $property->setName(idx(
      idx($node, 'Identifier', array()),
      'TrimmedText',
      null));
      
    $property->setType(idx(
      idx($node, 'PropertyType', array()),
      'TrimmedText',
      null));
    
    $property->setVisibility($this->getVisibilityOfMember($node));
    
    return $property;
  }
  
  private function loadField($member, $declaration, $declarator) {
    $field = new CSharpField();
    $field->setMemberASTNode($member);
    $field->setDeclarationASTNode($declaration);
    $field->setDeclaratorASTNode($declarator);
    
    $field->setName(idx(
      idx($declarator, 'Identifier', array()),
      'TrimmedText',
      null));
      
    $field->setType(idx(
      idx($declaration, 'DeclarationType', array()),
      'TrimmedText',
      null));
    
    $field->setVisibility($this->getVisibilityOfMember($member));
    
    return $field;
  }
  
}