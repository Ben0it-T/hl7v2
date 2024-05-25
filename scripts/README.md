# hl7v2 scripts

Create HL7 2.x json schemas and profiles from 2.x xsd schemas.  
HL7’s Version 2.x xsd schemas : [HL7 Version 2 Product Suite](https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185)

## Usage

### Create `config.php`

`config.php` is a basic configuration file script variables: 
- `inputDir`
- `outputDir`
- ...

Copy the `config-sample.php` to `config.php` and set script variables

### Create json schemas

Get HL7’s Version 2.x xsd schemas and Appendix from [HL7 Version 2 Product Suite](https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185)


Clean xsd schemas, formats output with indentation and extra space.
```
php xsd-schemas-cleaner.php
```

Generate json schemas from xsd schemas
```
php xsd-schemas-to-json-schemas.php
```

Update json schemas  
*Only from HL7 v2.3.1, v2.4, v2.5, v2.5.1 messaging schemas to Sun_HL7v2xsd*
```
php json-schemas-update-from-old-schemas.php
```

Update json schemas from Appendix A
```
php json-schemas-update-from-appendix-a.php
```

For HL7 2.5 IHE PAM FR, update json schemas to HL7 2.5 IHE PAM FR 2.x
```
php uptade-schemas-to-IHE-PAM-FR.php
```

### Create profiles

Create json profile from json schema  
*Set fieldsConstraints to true to apply 2.5 IHE PAM FR condition predicates*
```
php create-json-profile.php
```

Create xml profile (HL7v2xConformanceProfile) from json schema  
*Set fieldsConstraints to true to apply 2.5 IHE PAM FR condition predicates*
```
php create-xml-profile.php
```

