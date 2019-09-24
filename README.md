# CiviCRM: Joinery's Extra Reports

Some extra reports for CiviCRM, mostly created for Joinery's clients, but generalized
enough that they should be useful for everyone.

Reports in this extension:

* **Related Activities**: If I'm meeting with Bill next week, who else might have 
meetings with Bill, or with others in his company? Start with all the contacts 
who have activities with the  current user; add all contacts related to them 
(through as many as two hops, so, e.g. siblings AND individuals working for the 
same company), and display all activities for all those contacts. Filter by
activity type, date range, relationship type, etc.

The extension is licensed under [GPL-3.0](LICENSE.txt).

## Requirements

* PHP v5.4+
* CiviCRM 5.x

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl jreports@https://github.com/twomice/civicrm-jreports/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) 
repo for this extension and install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/twomice/civicrm-jreports.git
cv en jreports
```

## Usage

Report templates are automatically made available upon installation. Navigate to
_Administer_ > _CiviReport_ > _Create new report from template_


## Support
Paid support is available for urgent fixes or large; occasional free support for
easy bug fixes and "great ideas I like and have time for". Very likely to answer
questions about what's possible and to provide pointers if you have any trouble.
For any of the above, please create a ticket in the
[issue queue](https://github.com/twomice/civicrm-jreports/issues).