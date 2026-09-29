[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate/?business=SAT23GPU7F6AS&no_recurring=1&currency_code=EUR)
# Community Builder user list module

## Description
Shows the users of a Community Builder (CB) list in a Joomla module, with a template you write yourself.
Every CB and Joomla user field can be inserted with `[fieldname]` tags.

Version 4 is a rewrite for Joomla 4.4, 5 and 6 using the native module structure (namespaced dispatcher, service provider and layout).
It contains all improvements from the [Tazzios fork](https://github.com/Tazzios/cblistmodule) up to 3.6.0 plus security hardening.

You can also find it on the Joomla extensions directory: https://extensions.joomla.org/extension/extension-specific/community-builder-extensions/community-builder-list/

## Requirements
- Joomla 4.4, 5.x or 6.x
- PHP 8.1 or newer
- Community Builder 2.x

## Examples
Example of presentation in front-end:
![cblistfront](https://user-images.githubusercontent.com/23451105/120665837-6a21d600-c48c-11eb-9815-c243f2310b37.png)

Back-end configuration:
![config](config.png)

## Configuration
The only mandatory setting is the CB list to show users from. Everything else has a default.

### Tags
- `[cb_fieldname]`, `[name]`, `[username]` and so on insert a CB or Joomla user field.
- Image fields (`[avatar]`, `[canvas]`) become a full image URL, and only approved images are shown.
- Select, radio, multiselect and multicheckbox fields show their labels instead of the stored values.
- `[id]` and `[user_id]` insert the user id.
- `[profile_url]` inserts the link to the user's CB profile.
- `[site_url]` inserts the site root URL.
- A tag with the name of a rule is replaced by that rule (see below).
- Unknown tags are left as they are so you can spot typos.

### Template examples
``` html
<div class="yourclasstostyle"><p>[firstname] [lastname]<br/>[cb_yourfield]</p></div>
<div class="yourclasstostyle">[avatar]<br /> <a href="[profile_url]">[name]</a>
<div class="role"><a href="departments/[cb_department]">[cb_department]</a>, [cb_role]</div>
```

### Rules
A rule replaces a tag with your own HTML, one version for when the field has data and one for when it is empty.
A basic set of rules is created with every new module.

- A rule with the **same name as a CB field** wraps that field, for example `avatar` becoming `<img src="[avatar]">`.
  Inside the rule, `[avatar]` is the raw field value.
- A rule with a **new name** creates a custom tag, for example `show_avatar`. Custom tags always use the "data available" HTML.
- Rules can use other tags, including other rules. Make sure the tags you use inside a custom tag always have a value.
- Every rule has an **access level**. Only visitors with one of the selected Joomla access levels see the output.
  To restrict an existing field like `cb_salary`, create a rule named `cb_salary` with HTML `[cb_salary]` and the access level you want.

## Security notes
- Values users entered in their profile are HTML-escaped before they are inserted (setting "Escape field values").
  Turn this off only if your CB fields deliberately contain HTML.
- The module template, the "text above/below" and the rule HTML are administrator content and are output as is.
- The "Advanced" filter of a CB list is raw SQL written by the CB administrator, exactly as CB executes it.
  Basic filters are quoted and validated by the module.
- Debug output (the generated SQL) is only shown to logged in Super Users.
- The module does not apply CB privacy settings; it shows whatever fields you put in the template to everyone allowed to see the module and the rule.

## Upgrading from 3.x
Install version 4 over the old one. Module settings are kept. The legacy files (`mod_cblistmodule.php`, `helper.php`, `cblisthelper.php`) are removed automatically.
The default rules of new modules use `[profile_url]` instead of the relative `cb-profile/[user_id]` link; existing modules keep their own rules.

## Building a release
Run `./build.sh` to create `dist/mod_cblistmodule-<version>.zip`. Pushing a tag `v*` builds the same zip in GitHub Actions and attaches it to the release, which is what `updates.xml` points to.
