<?php

final class CSharpMethod extends Phobject {

  private $astNode;
  private $visibility;
  private $returnType;
  private $name;
  
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
  
  public function setReturnType($type) {
    $this->returnType = $type;
    return $this;
  }
  
  public function getReturnType() {
    return $this->returnType;
  }
  
  public function setName($name) {
    $this->name = $name;
    return $this;
  }
  
  public function getName() {
    return $this->name;
  }
  
}