<?php

final class CSharpClass extends Phobject {

  const TYPE_CLASS = 'class';
  const TYPE_STRUCT = 'struct';

  private $astNode;
  private $visibility;
  private $type;
  private $name;
  private $fields;
  private $properties;
  private $methods;
  private $nestedClasses;
  private $baseTypes;
  private $isPartial;
  
  public function setASTNode(array $node) {
    $this->astNode = $node;
    return $this;
  }
  
  public function getASTNode() {
    return $this->astNode;
  }
  
  public function setVisibility($visibility) {
    $this->visibility = $visibility;
    return $this;
  }
  
  public function getVisibility() {
    return $this->visibility;
  }
  
  public function setType($type) {
    $this->type = $type;
    return $this;
  }
  
  public function getType() {
    return $this->type;
  }
  
  public function setName($name) {
    $this->name = $name;
    return $this;
  }
  
  public function getName() {
    return $this->type;
  }
  
  public function setIsPartial($partial) {
    $this->isPartial = $partial;
  }
  
  public function isPartial() {
    return $this->isPartial;
  }
  
  private function filterByVisibility($items, $visibility) {
    $filtered_items = array();
    foreach ($items as $item) {
      if ($item->getVisibility() === $visibility) {
        $filtered_items[] = $item;
      }
    }
    return $filtered_items;
  }
  
  public function setFields(array $fields) {
    assert_instances_of($fields, 'CSharpField');
    $this->fields = $fields;
    return $this;
  }
  
  public function getFields($visibility = null) {
    if ($visibility === null) {
      return $this->fields;
    } else {
      return $this->filterByVisibility($this->fields, $visibility);
    }
  }
  
  public function setProperties(array $properties) {
    assert_instances_of($properties, 'CSharpProperty');
    $this->properties = $properties;
    return $this;
  }
  
  public function getProperties($visibility = null) {
    if ($visibility === null) {
      return $this->properties;
    } else {
      return $this->filterByVisibility($this->properties, $visibility);
    }
  }
  
  public function setMethods(array $methods) {
    assert_instances_of($methods, 'CSharpMethod');
    $this->methods = $methods;
    return $this;
  }
  
  public function getMethods($visibility = null) {
    if ($visibility === null) {
      return $this->methods;
    } else {
      return $this->filterByVisibility($this->methods, $visibility);
    }
  }
  
  public function setNestedClasses(array $nested_classes) {
    assert_instances_of($nested_classes, 'CSharpClass');
    $this->nestedClasses = $nested_classes;
    return $this;
  }
  
  public function getNestedClasses($visibility = null) {
    if ($visibility === null) {
      return $this->nestedClasses;
    } else {
      return $this->filterByVisibility($this->nestedClasses, $visibility);
    }
  }

}