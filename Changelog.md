v4.0.0 September 2026  
New: Rewritten as a native Joomla 4.4 / 5 / 6 module (namespaced dispatcher, service provider, helper factory, tmpl layout, installer script). Requires PHP 8.1 or newer.  
New: Language files (en-GB, sv-SE) and translatable module settings.  
New: `[profile_url]` and `[site_url]` tags. New modules link to the profile with `[profile_url]`.  
New: "Escape field values" setting, on by default, to stop users injecting HTML or scripts through their profile fields.  
New: Setting "Number of users" 0 shows the whole list.  
Merged from Tazzios 3.0.4 to 3.6.0: access level per rule (#12), calculated fields removed from the order list (#16), image detection by the `approved` column, PHP 8.1 null and array fixes (#7, #8, #10, #15), escaped filter values (#11), list query split into its own class.  
Security: all SQL is built with the Joomla query builder, quoted identifiers and bound or quoted values. Column names from the CB list configuration are validated. Password, OTP and reset columns are never exposed as tags.  
Security: user supplied values are never scanned for tags, only the administrator's template and rule HTML are.  
Security: debug output is only shown to logged in Super Users.  
Fix: a "Contains" (LIKE) filter dropped all filters before it.  
Fix: an "In" filter used the whole value for every element and broke the AND separators after it.  
Fix: `SELECT DISTINCT ... ORDER BY` failed on MySQL 5.7+ when ordering by a column outside the select list. Group membership is now an EXISTS subquery.  
Fix: `IS NULL` / `IS NOT NULL` and "starts with" / "ends with" filter operators are supported; unknown operators are skipped instead of injected.  
Improve: one query per page for users and one for field labels instead of one or more per user.  
Improve: labels are looked up per field id, so two fields sharing the same stored value get the right label.  
Improve: a self referencing or circular rule cannot loop.  
Removed: legacy files `mod_cblistmodule.php`, `helper.php`, `cblisthelper.php`, `index.html` and the `.idea` folder.  

v3.6.0 September 2026 (Tazzios)  
New: J6 native compatible  

v3.2.0 Jul 2024 (Tazzios)  
New: J5 native compatible   
Fix: remove calculated fields from filter options #16  
Improve: Set default access level #14  
Improve: PHP warning Passing null to parameter #15  

v3.1.0 jan 2024 (Tazzios)  
Add: field autorisation, also works for existing fields #12  
Improved: removed a foreach for.  
Improved: Order of the rules should have less influence on the result  
Fix: Escape special chars for value #11  
Fix: Fix cast array Update helper.php #10 (@magnushasselquist)  
  
v3.0.4 oct 2023 (Tazzios)  
Improve: PHP warnings when multifield options has changes #7 (@magnushasselquist)  
Improve: PHP warnings when a list is not filtered #8 (@magnushasselquist)  
Improve: Split the createcblist code to a seperate function for maintenance.  
Improve: Removed tmpl folder because not used.  
  
v3.0.3 febr 2022  
Fix: Broke the image when there was no avatar (fix is for new modules only) #2  
Improve: Checked J4 compactibility #3  
Improve: Show label instead of value for multicheckbox, multiselect, select and radio field types #4  
  
v3.0.2 oct 2021  
Fix: Changed default setting to prevent bug #2  
  
v3.0.1 sept 2021  
Fix: User double shown if in multiple groups #1  
  
v3.0 jun 2021  
New: function to replace tags with own HTML codes one for if data is available en one for when it is not. This way you do not have to work with script code.  
Improved: Only approved images are shown.  
Improved: code rewrite sql improvement, prevent retrieving unnecessary requests.  

v2.3 may 2021  
Add: [sort order options (including random)](https://github.com/magnushasselquist/hqcblistmodule/pull/16)  
Improve: [Keep text inside div](https://github.com/magnushasselquist/hqcblistmodule/pull/13)  
Fix: [contains filter did not work](https://github.com/magnushasselquist/hqcblistmodule/pull/15)  
Fix: [Only show published lists](https://github.com/magnushasselquist/hqcblistmodule/pull/14)  
Fix: [usergroup selection](https://github.com/magnushasselquist/hqcblistmodule/pull/17)  

v2.2 may 2021  
New: [limit users option to not show the complete list.] (https://github.com/magnushasselquist/hqcblistmodule/pull/4)  
Fix: [Made the module working again with list with filters.] (https://github.com/magnushasselquist/hqcblistmodule/pull/3)  
Fix: autoupdate of the module  

v2.1.2 jul 2018  
Version of magnushasselquist https://github.com/magnushasselquist/hqcblistmodule/releases  
