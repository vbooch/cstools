<?php

final class MethodDeclarationParametersOnNewlineCSharpLintPolicy
  extends CSharpLintPolicy {

  const MOVE_PARAMETER_ONTO_NEWLINE = '1';

  public function getCode() {
    return 'MNEWLINE';
  }
  
  public function getName() {
    return pht('Method Parameters on Newlines');
  }
  
  public function getDescription() {
    return pht(
      'Ensures that method parameters are indented and placed '.
      'on new lines if a method has more than 2 parameters '.
      'declared, or if the length of the method declaration is greater '.
      'than 60 characters.');
  }
  
  public function getLintSeverityMap() {
    return array(
      self::MOVE_PARAMETER_ONTO_NEWLINE => 
        ArcanistLintSeverity::SEVERITY_ERROR,
    );
  }
  
  public function getLintNameMap() {
    return array(
      self::MOVE_PARAMETER_ONTO_NEWLINE =>
        pht('Move parameter onto new line'),
    );
  }
  
  public function analyzeNode($path, array $node, array $parents) {
    if ($this->getType($node) === 'MethodDeclarationSyntax' ||
      $this->getType($node) === 'ConstructorDeclarationSyntax') {
      $parameter_list = $this->findChildWithType(
        $node,
        'ParameterListSyntax');
      $parameter_syntaxes = $this->findChildrenWithType(
        $node,
        'ParameterSyntax',
        true,
        $parents);
      
      $should_adjust_parameters = false;
      if (strlen($this->getTrimmedText($parameter_list)) > 60) {
        $should_adjust_parameters = true;
      } else if (count($parameter_syntaxes) > 2) {
        $should_adjust_parameters = true;
      }
      
      if ($should_adjust_parameters) {
        $line = $this->getStartLine($node);
        $added_lines = 0;
        for ($i = 0; $i < count($parameter_syntaxes); $i++) {
          $target_line = $line + $i + 1 - $added_lines;
          $parameter_syntax = $parameter_syntaxes[$i][0];
          $parameter_syntax_parents = $parameter_syntaxes[$i][1];
          if ($this->getStartLine($parameter_syntax) < $target_line) {
            list($token, $token_parents) = $this->findFirstTokenOfNode(
              $parameter_syntax,
              true,
              $parameter_syntax_parents);
            list($whitespace, $offset) = $this->getWhitespaceWithOffsetBeforeToken(
              $token,
              $token_parents);
            $this->raiseLintAtOffset(
              $offset,
              self::MOVE_PARAMETER_ONTO_NEWLINE,
              'This parameter should be placed on a new line.',
              $whitespace.
              $this->getText($token),
              "\n".
              $this->getIndentation($token, $token_parents).
              $this->getText($token));
            $added_lines++;
          }
        }
      }
    }
  
  }
  
  public function analyzeToken($path, array $token, array $parents) {
  }
  
}