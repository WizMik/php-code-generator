<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace gossi\codegen\visitor;

use gossi\codegen\model\GenerateableInterface;
use gossi\codegen\model\AbstractPhpStruct;
use gossi\codegen\model\PhpFunction;
use gossi\codegen\model\PhpInterface;
use gossi\codegen\model\PhpTrait;
use gossi\codegen\model\PhpClass;
use gossi\codegen\model\ConstantsInterface;
use gossi\codegen\model\TraitsInterface;

/**
 * The default navigator.
 *
 * This class is responsible for the default traversal algorithm of the different
 * code elements.
 *
 * Unlike other visitor pattern implementations, this allows to separate the
 * traversal logic from the objects that are traversed.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class DefaultNavigator {

	private $constantSortFunc;

	private $propertySortFunc;

	private $methodSortFunc;

	private static $defaultMethodSortFunc;

	private static $defaultPropertySortFunc;

	/**
	 * Sets a custom constant sorting function.
	 *
	 * @param null|\Closure $func        	
	 */
	public function setConstantSortFunc(\Closure $func = null) {
		$this->constantSortFunc = $func;
	}

	/**
	 * Sets a custom property sorting function.
	 *
	 * @param null|\Closure $func        	
	 */
	public function setPropertySortFunc(\Closure $func = null) {
		$this->propertySortFunc = $func;
	}

	/**
	 * Sets a custom method sorting function.
	 *
	 * @param null|\Closure $func        	
	 */
	public function setMethodSortFunc(\Closure $func = null) {
		$this->methodSortFunc = $func;
	}

	public function accept(GeneratorVisitorInterface $visitor, GenerateableInterface $model) {
		if ($model instanceof AbstractPhpStruct) {
			$this->visitStruct($visitor, $model);
		} else if ($model instanceof PhpFunction) {
			$visitor->visitFunction($model);
		}
	}

	private function visitStruct(GeneratorVisitorInterface $visitor, AbstractPhpStruct $struct) {
		// start struct
		if ($struct instanceof PhpInterface) {
			$visitor->startVisitingInterface($struct);
		} else if ($struct instanceof PhpTrait) {
			$visitor->startVisitingTrait($struct);
		} else if ($struct instanceof PhpClass) {
			$visitor->startVisitingClass($struct);
		}
		
		// contents
		if ($struct instanceof ConstantsInterface) {
			$constants = $struct->getConstants(true);
			if (!empty($constants)) {
				uksort($constants, $this->getConstantSortFunc());
				
				$visitor->startVisitingStructConstants();
				foreach ($constants as $constant) {
					$visitor->visitStructConstant($constant);
				}
				$visitor->endVisitingStructConstants();
			}
		}
		
		if ($struct instanceof TraitsInterface) {
			$properties = $struct->getProperties();
			if (!empty($properties)) {
				usort($properties, $this->getPropertySortFunc());
				
				$visitor->startVisitingProperties();
				foreach ($properties as $property) {
					$visitor->visitProperty($property);
				}
				$visitor->endVisitingProperties();
			}
		}
		
		$methods = $struct->getMethods();
		if (!empty($methods)) {
			usort($methods, $this->getMethodSortFunc());
			
			$visitor->startVisitingMethods();
			foreach ($methods as $method) {
				$visitor->visitMethod($method);
			}
			$visitor->endVisitingMethods();
		}
		
		// end struct
		if ($struct instanceof PhpInterface) {
			$visitor->endVisitingInterface($struct);
		} else if ($struct instanceof PhpTrait) {
			$visitor->endVisitingTrait($struct);
		} else if ($struct instanceof PhpClass) {
			$visitor->endVisitingClass($struct);
		}
	}

	private function getConstantSortFunc() {
		return $this->constantSortFunc ?  : 'strcasecmp';
	}

	private function getMethodSortFunc() {
		if (null !== $this->methodSortFunc) {
			return $this->methodSortFunc;
		}
		
		if (empty(self::$defaultMethodSortFunc)) {
			self::$defaultMethodSortFunc = function ($a, $b) {
				if ($a->isStatic() !== $isStatic = $b->isStatic()) {
					return $isStatic ? 1 : -1;
				}
				
				if (($aV = $a->getVisibility()) !== $bV = $b->getVisibility()) {
					$aV = 'public' === $aV ? 3 : ('protected' === $aV ? 2 : 1);
					$bV = 'public' === $bV ? 3 : ('protected' === $bV ? 2 : 1);
					
					return $aV > $bV ? -1 : 1;
				}
				
				return strcasecmp($a->getName(), $b->getName());
			};
		}
		
		return self::$defaultMethodSortFunc;
	}

	private function getPropertySortFunc() {
		if (null !== $this->propertySortFunc) {
			return $this->propertySortFunc;
		}
		
		if (empty(self::$defaultPropertySortFunc)) {
			self::$defaultPropertySortFunc = function ($a, $b) {
				if (($aV = $a->getVisibility()) !== $bV = $b->getVisibility()) {
					$aV = 'public' === $aV ? 3 : ('protected' === $aV ? 2 : 1);
					$bV = 'public' === $bV ? 3 : ('protected' === $bV ? 2 : 1);
					
					return $aV > $bV ? -1 : 1;
				}
				
				return strcasecmp($a->getName(), $b->getName());
			};
		}
		
		return self::$defaultPropertySortFunc;
	}
}
