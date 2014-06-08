A model behavior adding several very helpful utility methods for dealing with model attributes.
=============================================================================================

Following is a brief description of each attribute utility this behavior will add to your model.

 1. Differentiation between database attribute names and virtual attribute names
 You may differentiate between virtual and database attribute names by defining your virtual attribute names in the
 virtualAttributeNames() method in your model the same as you would in the CModel::attributeNames() method.

 2. Aggregate all attribute names at once.
 Use the getAllAttributeNames() method to get a list of all attributes of your model
 including column attributes, virtual attributes, and relations.

 3. Determine the CFormatter type of an attribute.
 Use the see getAttributeType() method method to get the type of an attribute.
 You may define the type of an attribute by implementing the attributeTypes() method in your model.
 If you do not define a type for an attribute then the type will be determined automatically for you
 by analyzing the database column type and/or relation type and/or the validators defined for the attribute
 see generateAttributeType() method

 4. Get a list of required attributes
 see getRequiredAttributes() method

 5. Get whether an attribute is optional (not required)
 see isAttributeOptional() method

 6. Get a list of optional attributes.
 see getOptionalAttributes() method

 6. Convert you model's attributes to an array.
 see toArray() method This method also works recursively so that you may extract related model attributes as a nested arrays of values.

 7. Get your model's errors as a JSON encoded string.
 see getErrorsAsJSON() method
