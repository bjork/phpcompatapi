<?php

use Bartlett\CompatInfo\Analyser\CompatibilityAnalyser;
use PhpParser\Node;

class ExtendedCompatibilityAnalyser extends CompatibilityAnalyser {

	/**
	 * Called when leaving a node.
	 *
	 * @param Node $node Node
	 *
	 * @return null|Node|false|Node[] Node
	 */
	public function leaveNode(Node $node)
	{
		parent::leaveNode( $node );

		if ( $node instanceof Node\Expr\Empty_ ) {
			$this->computePhpEmptyFeatureVersions( $node );
		}
	}

	/**
	 * Compute the version of a specific PHP feature:
	 * call of empty() with an arbitrary expression.
	 *
	 * @param Node $node
	 *
	 * @return void
	 */
	private function computePhpEmptyFeatureVersions(Node $node) {

		if ( $node instanceof Node\Expr\Empty_ ) {

			// If the parameter of empty() is an arbitrary expression,
			// and not just a variable.
			if ( $node->expr instanceof Node\Expr
				&& ! $node->expr instanceof Node\Expr\Variable
				&& ! $node->expr instanceof Node\Expr\ArrayDimFetch
				&& ! $node->expr instanceof Node\Expr\PropertyFetch ) {

				// Prior to PHP 5.5, empty() only supports variables
				// http://php.net/manual/en/function.empty.php
				$versions = array( 'php.min' => '5.5.0' );
				$this->updateLocalVersions( $versions );
			}

		}
	}
}