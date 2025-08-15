====== ifday Plugin ======

---- plugin ----
description: Enables <ifday> syntax for conditional display based on weekday, weekend, day names, with complex logic and parentheses
author     : dWiGhT Mulcahy
email      : dWiGhT.Codes@gmail.com
type       : Sytax
lastupdate : 2025-08-14
compatible : Librarian, Kaos, Jack Jackrum, Igor, Hogfather
depends    :
conflicts  :
similar    :
tags       : date

downloadurl: https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/releases/download/v1.0.0/ifday.zip
bugtracker : https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/issues
sourcerepo : https://github.com/dwightmulcahy/dokuwiki-plugin-ifday
donationurl: https://www.paypal.me/myloot

screenshot_img : # URL to a screenshot of the plugin in action

----


===== Installation =====

Search and install the plugin using the [[plugin:extension|Extension Manager]].

or to manually install this plugin:
- Extract the ''ifday'' folder into your DokuWiki ''lib/plugins/'' directory.
- Make sure the plugin is enabled (usually enabled by default).
- Use ''<ifday>'' tags with conditions in your wiki pages as described above.
- Configure error message visibility from **Admin → Configuration Settings → ifday → Show errors**.

===== Description =====

This plugin adds the ''<ifday>'' syntax block to conditionally render content based on date-related conditions.

It supports:

* Multi-condition logic with ''AND'', ''OR'', ''&&'', ''||''
* Comparison operators: `==`, `!=`, `<`, `>`, `<=`, `>=`
* Alias operator: `is` (treated as `==`)
* Negation operator: `NOT` (treated as logical NOT `!`)
* Parentheses for grouping conditions
* Special date keywords: `weekday`, `weekend`, and `day` (with specific day names like `monday`, `tuesday`, etc.)
* Day name abbreviations (`mon`, `tue`, `wed`, etc.) are supported and normalized
* Boolean checks (e.g., `<ifday weekday>`) without explicit comparisons
* `<else>` block support
* Configurable option to show/hide error messages on the wiki page for invalid conditions


===== Examples/Usage =====

==== Basic Usage ====

Wrap any content inside ''<ifday>'' tags with your condition expression:

<code>
<ifday weekday>
This content appears only on weekdays (Monday to Friday).
</ifday>

<ifday weekend>
This content appears only on weekends (Saturday and Sunday).
</ifday>

<ifday day is monday>
This content appears only on Mondays.
</ifday>

<ifday Wednesday>
This content appears only on Wednesdays.
<else>
This appears on days that are not Wednesday.
</ifday>
</code>

----

==== Comparison Operators ====

You can compare ''day'', ''weekday'', and ''weekend'' using:

| Operator | Meaning                                  | Example                      |
| `==` or `is` | Equals                               | `day == friday` or `day is friday` |
| `!=` of `is not`     | Does not equal       | `day != sunday`  or `is not weekday` |
| `<`, `>`, `<=`, `>=` | Numerical/time comparisons (limited use) | **Not typically used for day names** |

Example:

<code>
<ifday day != saturday AND day != sunday>
This content appears on all weekdays except weekends.
</ifday>
</code>

----

==== Logical Operators, Negation, and Grouping ====

Combine conditions with:

* ''AND'' or ''&&'' (logical AND)
* ''OR'' or ''||'' (logical OR)
* ''NOT'' (logical negation, e.g. ''NOT weekday'')
* Parentheses ''('' and '')'' to control evaluation order

Examples:

<code>
<ifday NOT weekday>
Content visible only on weekends.
</ifday>

<ifday (weekday AND day != friday) OR weekend>
Content visible if today is a weekday except Friday, or if it is the weekend.
</ifday>
</code>

----

==== Boolean Checks Without Explicit Comparisons ====

Simply using ''weekday'', ''weekend'', or day name (''monday'', ''tuesday'', etc.) without a comparison will evaluate to true or false automatically:

<code>
<ifday weekday>
Only visible on Monday through Friday.
</ifday>

<ifday weekend>
Only visible on Saturday or Sunday.
</ifday>

<ifday Monday>
Only visible on Monday.
</ifday>
</code>

----

==== Day Name and Abbreviation Checks ====

You can check for specific days using full names or 3-letter abbreviations (case-insensitive):

<code>
<ifday day == tuesday>
Visible only on Tuesday.
</ifday>

<ifday day is wed>
Visible only on Wednesday.
</ifday>

<ifday day != thu>
Hidden on Thursday, visible on other days.
</ifday>

<ifday thu>
Visible on Thursday only.
</ifday>
</code>

----

==== Shorthand Day-Only Syntax ====

You can simplify conditions that check for a specific day by omitting the full comparison syntax.

For example, these are equivalent:

<code>
<ifday day is monday>
Content visible only on Mondays.
</ifday>

<ifday monday>
Content visible only on Mondays.
</ifday>

<ifday mon>
Content visible only on Mondays (using 3-letter abbreviation).
</ifday>
</code>

----

==== Mixed Conditions ====

Combine day names and ''weekday''/''weekend'' status:

<code>
<ifday (day == mon OR day == wed) AND weekday>
Visible only on Monday or Wednesday during the weekdays.
</ifday>

<ifday weekend AND day != sun>
Visible on Saturdays only.
</ifday>
</code>

===== Notes =====

* The plugin evaluates conditions based on the **server’s current date** and time.
* Logical operators (''AND'', ''OR'', ''NOT'') and comparison operators (''is'', ''=='', ''!='') are **case-insensitive**.
* Parentheses are supported for grouping and clarifying complex logic.
* Invalid or unsafe expressions cause the condition to evaluate as false and produce an error message or log.
* The plugin currently does **not support time-of-day or date comparisons**, only day-based logic.
* Day names and abbreviations are normalized internally to ensure flexible matching.

===== Configuration and Settings =====

Configure error message visibility from **Admin → Configuration Settings → ifday → Show errors**.

===== Development =====

The source code of the plugin is available at GitHub: https://github.com/dwightmulcahy/dokuwiki-plugin-ifday.

=== Changelog ===

{{rss>https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/commits/main.atom date 8}}

=== Known Bugs and Issues ===

{{rss>https://rss-bridge.org/bridge01/?action=display&context=Project+Issues&u=dwightmulcahy&p=dokuwiki-plugin-ifday&c=on&bridge=GithubIssueBridge&format=Atom}}

=== ToDo/Wish List ===

- Nothing to see here.

===== FAQ =====

