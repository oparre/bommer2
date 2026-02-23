-- Remove foreign key constraint on component_id to allow polymorphic relationships
-- This allows component_id to reference either components or erp_components table
-- depending on the component_source value

USE bommer_auth;

-- Drop the foreign key constraint
ALTER TABLE bom_items DROP FOREIGN KEY bom_items_ibfk_2;

-- The component_id column remains, but without the foreign key constraint
-- Validation of component existence should be done at application level
