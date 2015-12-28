<?php

namespace gymadarasz\xparser;

class XNode {
	
	private $__xhtml = null;
	
	private $__source = null;
	
	private $__temps = [];
	
	public function __construct($xhtml = null, XNode &$source = null) {
		$this->outer($xhtml);
		$this->__source = &$source;
	}
	

	private function replace($search, $replace) {
		// replace only first occurrence cause more than one are in parent then it have to be a list instead an element
		$pos = strpos($this->__xhtml, $search);
		if ($pos !== false) {
			$this->__xhtml = substr_replace($this->__xhtml, $replace, $pos, strlen($search));
		}
		return $this;
	}

	public function outer($xhtml = null, $restore = true) {
		if(is_null($xhtml)) {
			if($restore) {
				return $this->restored();
			}
			else {
				return $this->__xhtml;
			}
		}
		else {
			if(!is_null($this->__source) && !is_null($this->__xhtml)) {
				$this->__source->replace($this->__xhtml, $xhtml);
			}
			$this->__xhtml = $xhtml;
			$this->cleanup();
			return $this;
		}
	}
	
	private static function getInner($xhtml) {
		preg_match('/<\w+\b.*?>([\w\W]*)<\/\w+>/is', $xhtml, $match);
		return $match[1];
	}

	public function inner($xhtml = null) {
		if(is_null($xhtml)) {
			if($this->__xhtml) {
				return self::getInner($this->__xhtml);
			}
			trigger_error('XHTML Parse Error: A requested element has no inner text.', E_USER_NOTICE);
			return null;
		}
		else {
			$__xhtml = $this->__xhtml;
			
			$this->__xhtml = preg_replace('/(<\w+\b.*?>)([\w\W]*)(<\/\w+>)/is', '$1' . $xhtml . '$3', $this->__xhtml);
			
			if(!is_null($this->__source)) {
				$this->__source->replace($__xhtml, $this->__xhtml);
			}
			return $this;
		}
	}


	private function getPossibleTags() {
		// todo : order the result by occurrence rate for more performance!
		preg_match_all('/<(\w+)\b/si', $this->__xhtml, $matches);
		return array_unique($matches[1]);
	}
	
	private function getElementFirst($tag = null, $attr = '\w*', $value = '\w*') {
		return $this->getElementsArray($tag, $attr, $value, true);
	}
	
	private function getElementsArray($tag = null, $attr = '\w*', $value = '\w*', $one = false) {
	
		$founds = [];
		
		if(is_null($tag)) {			
			foreach($this->getPossibleTags() as $tag) {
				$founds = array_merge($founds, $this->getElementsArray($tag, $attr, $value, $one));
			}
		}
		else {
			
 			$simples = ['\!doctype', 'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
			$simple = in_array(strtolower($tag), $simples);	
			
			$singles = ['\!doctype', 'html', 'body', 'head', 'title']; // todo more?
			$single = in_array(strtolower($tag), $singles);
			$one = $single || $attr == 'id';

			if($attr == '\w*' || $value == '\w*') {
				
				if($simple) {
					$regex = '/<' . $tag . '\b[^>]*[^>\/]*?>/is';
					preg_match_all($regex, $this->__xhtml, $matches);
					$founds = $matches[0];
					if($one && $founds) return [$founds[0]];
				}
				
				$regex = '/<' . $tag . '\b[^>]*[^>\/]*?\/>/is';
				preg_match_all($regex, $this->__xhtml, $matches);
				$founds = $matches[0];
				if($one && $founds) return [$founds[0]];

				$regex = '/<' . $tag . '\b[^>]*[^>\/]*?\>.*<\/' . $tag . '>/is';
				preg_match_all($regex, $this->__xhtml, $matches);
				$founds = array_merge($founds, $matches[0]);
				if($one && $founds) return [$founds[0]];
			}
			
			if($simple) {
				$regex = '/<' . $tag . '\b[^>]*\b' . $attr . '\b\s*?=\s*?"' . $value . '"[^>\/]*?>/is';
				preg_match_all($regex, $this->__xhtml, $matches);
				$founds = array_merge($founds, $matches[0]);
				if($one && $founds) return [$founds[0]];				
			}

			$regex = '/<' . $tag . '\b[^>]*\b' . $attr . '\b\s*?=\s*?"' . $value . '"[^>\/]*?\/>/is';
			preg_match_all($regex, $this->__xhtml, $matches);
			$founds = array_merge($founds, $matches[0]);
			if($one && $founds) return [$founds[0]];

			$regex = '/<' . $tag . '\b[^>]*\b' . $attr . '\b\s*?=\s*?"' . $value . '"[^>\/]*?>.*?<\/' . $tag . '>/is';
			preg_match_all($regex, $this->__xhtml, $matches);
			foreach($matches[0] as $match) {
				if(preg_match_all('/<' . $tag . '\b/', $match, $inmatch) == 1) {										
					if($one && $founds) return [$match];
					if(!in_array($match, $founds)) {
						$founds[] = $match;
					}
				}
			}

			if(!$single) {
				
				$regex = '/<' . $tag . '\b[^>]*\b' . $attr . '\b\s*?=\s*?"' . $value . '"[^>\/]*?>(\R|.*?<\/' . $tag . '>).*<\/' . $tag . '>/is';
				preg_match_all($regex, $this->__xhtml, $matches);

				$valids = array_keys($matches[0]);
				
				for($mkey=0; $mkey<count($matches[0]); $mkey++) {
					if(in_array($mkey, $valids)) {
						foreach ($founds as $found) {
							if (strpos($matches[0][$mkey], $found) !== false) {
								unset($valids[$mkey]);
							}
						}
					}
				}

				foreach($valids as $mkey) {
					if($one && $founds) return $matches[0][$mkey];
					$founds[] = $matches[0][$mkey];
				}
				
			}
			
		}

		return $founds;
	}


	public function getElements($tag = null, $attr = '\w*', $value = '\w*') {

		$founds = $this->getElementsArray($tag, $attr, $value);
	
		return new XNodeList($founds, $this);
	}
	
	public function getElementById($id) {
		return new XNode($this->getElementFirst(null, 'id', $id)[0], $this);
	}

	private function getElementsByClassArray($class) {
		return $this->getElementsByTagAndClassArray(null, $class);
	}
	
	private function getElementsByTagAndClassArray($tag, $class) {
		$results = $this->getElementsArray($tag, 'class', '[\w\s]*\b' . $class . '\b[\w\s]*');
		return $results;
	}

	public function getElementsByClass($class) {
		return new XNodeList($this->getElementsByClassArray($class), $this);
	}	

	public function find($select, $index = null) {
		$ret = new XNodeList([], $this);
		$selects = preg_split('/\s*,\s*/', $select);
		foreach($selects as $select) {
			$words = preg_split('/\s+/', trim($select));
			$founds = [];
			foreach($words as $wkey => $word) {
				preg_match_all('/([\.\#]?)(\w+)|()(\w+)/is', $word, $parse);
				$tag = null;
				$ids = [];
				$classes = [];
				foreach($parse[1] as $key => $type) {
					switch($type) {
						case '':
							$tag = $parse[2][$key];
						break;
						case '#':
							$ids[] = $parse[2][$key];
						break;
						case '.':
							$classes[] = $parse[2][$key];
						break;
						default:
							throw new XParserException('Invalid CSS selector: ' . $select);
						break;
					}
				}

				if(!$ids && !$classes) {
					$founds = $this->getElementsArray($tag);
				}
				else if($ids && !$classes) {
					foreach($ids as $id) {
						$founds = array_merge($founds, $this->getElementsArray($tag, 'id', $id));
					}
				}
				else if(!$ids && $classes) {					
					foreach($classes as $class) {
						$foundsByClass[$class] = $this->getElementsByTagAndClassArray($tag, $class);
					}				
					if(count($foundsByClass)>1) {
						$founds = array_merge($founds, call_user_func_array('array_intersect', $foundsByClass));
					}
					else {
						$founds = array_merge($founds, $foundsByClass[$class]);
					}
					
				}
				else if($ids && $classes) {				
					$foundsById = [];
					foreach($ids as $id) {
						$foundsById = array_merge($foundsById, $this->getElementsArray($tag, 'id', $id));
					}
					$foundsByClass = [];
					foreach($classes as $class) {				
						$foundsByClass = array_merge($foundsByClass, $this->getElementsByClassArray($class));
					}
					$founds = array_intersect($foundsById, $foundsByClass);
				}
				else {
					// hmmm.. interesting.
					throw new XParserException('?');
				}
				
				if(!$founds) {
					break;
				}
				
				if(isset($words[$wkey+1])) {
					$rest = implode(' ', array_slice($words, $wkey+1));
					foreach($founds as $found) {
						$inner = self::getInner($found);
						$innerElement = new XNode($inner, $this);
						$restElements = $innerElement->find($rest);
						foreach($restElements as $restElement) {
							$ret->addElement($restElement);
						}
					}
					return $ret;
				}
				
			}
			$ret->addElementsArray($founds, $this);
		}
		if(!is_null($index)) {
			return $ret->getElement($index);
		}
		return $ret;
	}
	
	public function attr($attr, $value = null) {
		$regex = '/(^[^>]*\b' . $attr . '\s*=\s*)"(.*?)"/is';
		if(is_null($value)) {
			preg_match($regex, $this->__xhtml, $matches);
			return isset($matches[2]) ? $matches[2] : null;
		}
		else {
			$xhtml = $this->__xhtml;
			
			$this->__xhtml = preg_replace($regex, '$1"' . $value . '"', $this->__xhtml, 1);
			
			if(!is_null($this->__source)) {
				$this->__source->replace($xhtml, $this->__xhtml);
			}			
			return $this;
		}		
	}
	
	private function cleanup($removeComments = false) {
		if(is_null($this->__source)) {
			$outer = $this->__xhtml;
			$regex = '/<\!--(.*?)-->/s';
			if($removeComments) {
				$outer = preg_replace($regex, '', $outer);
			}
			else {
				preg_match_all($regex, $outer, $matches);
				$this->__temps = $matches[0];
			}

			//$this->__temps = array_merge($this->__temps, $this->getElementsArray('script'), $this->getElementsArray('style'));

			foreach($this->__temps as $key => $temp) {
				$outer = str_replace($temp, '[XPARSER TEMP #' . $key . ']', $outer);
			}

			$this->__xhtml = $outer;
		}
		return $this;
	}
	
	private function restored() {
		$__xhtml = $this->__xhtml;
		if(is_null($this->__source)) {
			foreach($this->__temps as $key => $temp) {
				$__xhtml = preg_replace('/\[XPARSER TEMP \#' . $key . '\]/', $temp, $__xhtml);
			}
		}
		return $__xhtml;
	}
	
	public function __toString() {
		return $this->outer() . '';
	}
	
	public function __set($name, $value) {
		if(!property_exists($this, $name)) {
			$this->attr($name, $value);
		}
		else {
			$this->$name = $value;
		}
	}
	
	public function __get($name) {
		if(!property_exists($this, $name)) {
			$value = $this->attr($name);
		}
		else {
			$value = $this->$name;
		}
		return $value;
	}

	public function __invoke($select, $index = null) {
		return $this->find($select, $index);
	}

}
