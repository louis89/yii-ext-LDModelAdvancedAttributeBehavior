<?php
/**
 * LDModelAdvancedAttributeBehavior class file.
 *
 * @author Louis A. DaPrato <l.daprato@gmail.com>
 * @link https://lou-d.com
 * @copyright 2014 Louis A. DaPrato
 * @license The MIT License (MIT)
 * @since 1.0
 */

/**
 * This class provides several useful methods for dealing with model attributes.
 * 
 * 1. Differentiation between database attribute names and virtual attribute names
 * You may differentiate between virtual and database attribute names by defining your virtual attribute names in the 
 * {@link virtualAttributeNames} method in your model the same as you would in the {@link CModel::attributeNames} method.
 * 
 * 2. Aggregate all attribute names at once.
 * Use the {@link getAllAttributeNames} method to get a list of all attributes of your model 
 * including column attributes, virtual attributes, and relations.
 * 
 * 3. Determine the CFormatter type of an attribute.
 * Use the {@link getAttributeType} method to get the type of an attribute.
 * You may define the type of an attribute by implementing the {@link attributeTypes} method in your model.
 * If you do not define a type for an attribute then the type will be determined automatically for you
 * by analyzing the database column type and/or relation type and/or the validators defined for the attribute 
 * {@link generateAttributeType}
 * 
 * 4. Get a list of required attributes
 * {@link getRequiredAttributes}
 * 
 * 5. Get whether an attribute is optional (not required)
 * {@link isAttributeOptional}
 * 
 * 6. Get a list of optional attributes.
 * {@link getOptionalAttributes}
 * 
 * 6. Convert you model's attributes to an array.
 * {@link toArray} This method also works recursively so that you may extract related model attributes as a nested arrays of values.
 * 
 * 7. Get your model's errors as a JSON encoded string.
 * {@link getErrorsAsJSON}
 * 
 * @author Louis A. DaPrato <l.daprato@gmail.com>
 * @since 1.0
 *
 */
class LDModelAdvancedAttributeBehavior extends CModelBehavior
{
	
	/**
	 * Returns the list of virtual attribute names.
	 *
	 * @return array list of virtual attribute names.
	 */
	public function virtualAttributeNames()
	{
		return array();
	}
	
	/**
	 * Returns the list of attribute names and their respective CFormatter type of the model.
	 * The list should be formatted such that the keys are the attribute names and the values are the CFormatter types.
	 * 
	 * @return array list of attribute names and their respective CFormatter types.
	 */
	public function attributeTypes()
	{
		return array();
	}
	
	/**
	 * Returns the CFormatter type for the specified attribute.
	 * 
	 * @param string $attribute the attribute name
	 * @return string the CFormatter type
	 * @see generateAttributeType
	 * @see attributeTypes
	 */
	public function getAttributeType($attribute)
	{
		$model = $this->getOwner();
		$types = $model->attributeTypes();
		if(isset($types[$attribute]))
		{
			return $types[$attribute];
		}
		
		if($model instanceof CActiveRecord && strpos($attribute, '.') !== false)
		{
			$segs = explode('.', $attribute);
			$name = array_pop($segs);
			foreach($segs as $seg)
			{
				$relations = $model->getMetaData()->relations;
				if(!isset($relations[$seg]))
				{
					break;
				}
				$model = CActiveRecord::model($relations[$seg]->className);
			}
			return $model->getAttributeType($name);
		}
		
		return $model->generateAttributeType($attribute);
	}
	
	/**
	 * Generates a CFormatter type for the specified attribute.
	 * This method will examine determine the type for an attribute as follows:
	 * First, if the model is a CActiveRecord,
	 * the correct CFormatter type will be determined based on the database type of the attribute's column, if it exists.
	 * If the column does not exist, and the attribute is a relation with a __toString method defined then 'text' will be used for the type.
	 * 
	 * Second, each validator defined for the attribute will be examined and the appropriate type will be selected base on the type of validator.
	 * 
	 * Note that a type determined from a validator will always override any previous types including types determined by previous validators.
	 * This means the final validator that can be used to determine the type of an attribute will be the final type decided for that attribute by this method.
	 * 
	 * @param string $name the attribute name
	 * @return string the attribute type
	 */
	public function generateAttributeType($name)
	{
		$type = 'raw';
		$owner = $this->getOwner();
		if($owner instanceof CActiveRecord) 
		{
			if(isset($owner->getMetaData()->columns[$name])) // If this is an CActiveRecord check the database type for the attribute's column if it exists
			{
				$dbType = $owner->getMetaData()->columns[$name]->dbType;
				if(stripos($dbType, 'bool') !== false)
				{
					$type = 'boolean';
				}
				else if(preg_match('/(real|floa|doub|int|dec|numeric|fixed)/i', $dbType))
				{
					$type = 'number';
				}
				else if(preg_match('/(timestamp|datetime)/i', $dbType))
				{
					$type = 'datetime';
				}
				else if(stripos($dbType, 'date') !== false)
				{
					$type = 'date';
				}
				else if(stripos($dbType, 'time') !== false)
				{
					$type = 'time';
				}
				else 
				{
					$type = 'text';
				}
			}
			else // If the column did not exist see if it the attribute is a relation and the relation can be converted to a string. If so type is 'text'.
			{
				$relations = $owner->relations();
				if(isset($relations[$name]))
				{
					$reflection = new ReflectionClass($relations[$name][1]);
					if($reflection->hasMethod('__toString') && $reflection->getMethod('__toString')->isPublic())
					{
						$type = 'text';
					}
				}
			}
		}

		// Attempt to determine the type of the attribute from validators defined for the attribute. 
		// Note if a type can be determined from a validator then it will override any previous type determined for the attribute.
		foreach($owner->getValidators($name) as $validator)
		{
			if($validator instanceof CTypeValidator || ($validator instanceof CRequiredValidator && $validator->requiredValue !== null))
			{
				switch($validator instanceof CTypeValidator ? $validator->type : gettype($validator->requiredValue))
				{
					case 'integer':
					case 'float':
					case 'double':
						$type = 'number'; break;
					case 'string':
						$type = 'text'; break;
					case 'object':
						if($validator instanceof CRequiredValidator)
						{
							$reflection = new ReflectionClass($validator->requiredValue);
							if($reflection->hasMethod('__toString') && $reflection->getMethod('__toString')->isPublic())
							{
								$type = 'text'; break;
							}
						}
					case 'array':
					case 'resource':
					case 'NULL':
					case 'unknown type':
						$type = 'raw'; break;
					case 'date':
						$type = 'date'; break;
					case 'time':
						$type = 'time'; break;
					case 'datetime':
						$type = 'datetime'; break;
					case 'boolean':
						$type = 'boolean'; break;
				}
			}
			else if($validator instanceof CStringValidator || $validator instanceof CRegularExpressionValidator || $validator instanceof CCaptchaValidator)
			{
				$type = 'text';
			}
			else if($validator instanceof CEmailValidator)
			{
				$type = 'email';
			}
			else if($validator instanceof CUrlValidator)
			{
				$type = 'url';
			}
			else if($validator instanceof CNumberValidator)
			{
				$type = 'number';
			}
			else if($validator instanceof CBooleanValidator)
			{
				$type = 'boolean';
			}
			else if($validator instanceof CDateValidator)
			{
				$type = 'date';
			}
		}
		return $type;
	}

	/**
	 * Returns all attribute names. 
	 * Including virtual attributes, column attributes, and discrete related attributes.
	 * 
	 * @return array list of attribute names
	 */
	public function getAllAttributeNames($parent = null, $excludedAttributes = array())
	{
		$owner = $this->getOwner();
		$attributes = array_diff(array_merge($owner->attributeNames(), $owner->virtualAttributeNames()), $excludedAttributes);
		
		if(!$owner instanceof CActiveRecord)
		{
			return $attributes;
		}

		$tableSchema = $owner->getTableSchema();
		$dbSchema = $owner->getCommandBuilder()->getSchema();

		// Configure relations
		foreach($owner->relations() as $name => $config)
		{
			if(in_array($config[0], array(CActiveRecord::BELONGS_TO, CActiveRecord::HAS_ONE)) && ($parent === null || strcasecmp($config[1], $parent) !== 0))
			{
				$relatedModel = call_user_func(array($config[1], 'model'));
				$relatedTableSchema = $relatedModel->getTableSchema();
				$relatedKeys = array();
				$ownerKeys = is_string($config[2]) ? preg_split('/\s*,\s*/', $config[2], -1, PREG_SPLIT_NO_EMPTY) : $config[2];
				foreach($ownerKeys as $i => $ownerKey)
				{
					if(!is_int($i))
					{
						$relatedKey = $ownerKey;
						$ownerKey = $i;
					}
		
					if(!isset($tableSchema->columns[$ownerKey]))
					{
						throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key "{key}". There is no such column in the table "{table}".',
								array('{class}' => get_class($owner), '{relation}' => $name, '{key}' => $ownerKey, '{table}' => $tableSchema->name)));
					}
		
					if(is_int($i))
					{
						if(isset($tableSchema->foreignKeys[$ownerKey]) && $dbSchema->compareTableNames($relatedTableSchema->rawName, $tableSchema->foreignKeys[$ownerKey][0]))
						{
							$relatedKey = $tableSchema->foreignKeys[$ownerKey][1];
						}
						else // FK constraints undefined
						{
							if(is_array($relatedTableSchema->primaryKey)) // composite PK
							{
								$relatedKey = $relatedTableSchema->primaryKey[$i];
							}
							else
							{
								$relatedKey = $relatedTableSchema->primaryKey;
							}
						}
					}
					$relatedKeys[] = $relatedKey;
				}

				foreach($relatedModel->getAllAttributeNames(get_class($owner), $relatedKeys) as $attr)
				{
					$attributes[] = $name.'.'.$attr;
				}
			}
		}
		
		return $attributes;
	}
	
	/**
	 * Converts the model's errors to a JSON string where the model attribute's active ID is the key and the value is the error(s).
	 * 
	 * @return string A JSON exncoded string on success
	 */
	public function getErrorsAsJSON()
	{
		$result = array();
		$owner = $this->getOwner();
		foreach($owner->getErrors() as $attribute => $errors)
		{
			$result[CHtml::activeId($owner, $attribute)] = $errors;
		}
		return function_exists('json_encode') ? json_encode($result) : CJSON::encode($result);
	}
	
	/**
	 * Get a list of the attributes that are required by this model for successful validation
	 *  
	 * @param boolean $safeOnly Whether to only get safe required attributes. Defaults to True.
	 * @return array The names of the attributes that are required
	 */
	public function getRequiredAttributes($safeOnly = true)
	{
		$owner = $this->getOwner();
		return array_values(array_filter($safeOnly ? $owner->getSafeAttributeNames() : $owner->attributeNames(), array($owner, 'isAttributeRequired')));
	}
	
	/**
	 * Tests whether an attribute is optional (not required by this model for successful validation).
	 * 
	 * @param string $attribute The name of the attribute to test
	 * @return boolean True if the named attribute is not required. False otherwise.
	 * @see CModel::isAttributeRequired
	 */
	public function isAttributeOptional($attribute)
	{
		return !$this->getOwner()->isAttributeRequired($attribute);
	}
	
	/**
	 * Get a list of the attributes that are NOT required by this model for successful validation
	 * 
	 * @param boolean $safeOnly Whether to only get safe optional attributes. Defaults to True.
	 * @return array The names of the attributes that are optional
	 */
	public function getOptionalAttributes($safeOnly = true)
	{
		$owner = $this->getOwner();
		return array_values(array_filter($safeOnly ? $owner->getSafeAttributeNames() : $owner->attributeNames(), array($owner, 'isAttributeOptional')));
	}
	
	/**
	 * Extracts attribute values from the CModel this behavior is attached to into an array
	 * This method will recursively extract values whenever a key is a string in the attributes array.
	 * In that case the value is expected to be another list of attributes to be extracted from the object value stored in the attribute named by key.
	 *
	 * @param mixed $attributes The attribute values being extracted
	 * 	An array of the attributes to be extracted.
	 * 	A string of the attribute to be extracted.
	 * 	True extract all safe attributes.
	 * 	Null extract all attributes using {@link CModel::getAttributes} method.
	 * @param boolean $prefixWithClassName Whether to prefix each model's attribute group with the class name of the model.
	 */
	public function toArray($attributes = true, $prefixWithClassName = false)
	{
		return $this->_toArray($this->getOwner(), $attributes, $prefixWithClassName);
	}
	
	/**
	 * Helper method for recursively extracting attribute values from a model.
	 *
	 * @param CModel $model The model the attribute values are being extracted from
	 * @param mixed $attributes {@see toArray}
	 * @param boolean $prefixWithClassName {@see toArray}
	 * @return array The extracted attribute values.
	 */
	protected function _toArray($model, $attributes, $prefixWithClassName)
	{
		if($attributes === true) // If attributes is exactly equal to True return all safe attribute values.
		{
			return $this->_toArray($model, $model->getSafeAttributeNames(), $prefixWithClassName);
		}
	
		if(is_array($attributes)) // If attributes is an array loop over each attribute and extract its value.
		{
			$values = array();
			foreach($attributes as $key => &$value) // Loop over attribute names
			{
				if(is_string($key)) // If the key is a string then recursively extract attribute values from the related model
				{
					$values[$key] = $this->_toArray($model->$key, $value, $prefixWithClassName);
				}
				else // If the key is not a string just extract the attribute value
				{
					$values[$value] = $model->$value;
				}
			}
		}
		else if(is_string($attributes)) // If attributes is a string then we are extract a single attribute value
		{
			$values = array($attributes => $model->$attributes);
		}
		else // If attributes is not an array or a string or true then call CModel's getAttributes method
		{
			$values = $model->getAttributes($attributes);
		}
			
		return $prefixWithClassName ? array(get_class($model) => $values) : $values;
	}
	
}