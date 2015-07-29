<?php

final class CSharpField extends Phobject {

  private $memberASTNode;
  private $declarationASTNode;
  private $declaratorASTNode;
  private $visibility;
  private $type;
  private $name;

  public function setMemberASTNode(array $node) {
    $this->memberASTNode = $node;
    return $this;
  }
  
  public function getMemberASTNode() {
    return $this->memberASTNode;
  }

  public function setDeclarationASTNode(array $node) {
    $this->declarationASTNode = $node;
    return $this;
  }
  
  public function getDeclarationASTNode() {
    return $this->declarationASTNode;
  }

  public function setDeclaratorASTNode(array $node) {
    $this->declaratorASTNode = $node;
    return $this;
  }
  
  public function getDeclaratorASTNode() {
    return $this->declaratorASTNode;
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
    return $this->name;
  }
  
}