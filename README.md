# ifday Plugin for DokuWiki

[![License](https://img.shields.io/github/license/dwightmulcahy/dokuwiki-plugin-ifday?style=flat-square)](LICENSE)
[![DokuWiki](https://img.shields.io/badge/DokuWiki-compatible-blue?style=flat-square)](https://www.dokuwiki.org/)
[![PR Check](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/actions/workflows/pr-check.yml/badge.svg)](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/actions/workflows/pr-check.yml)
<br>
[![Latest Release](https://img.shields.io/github/v/release/dwightmulcahy/dokuwiki-plugin-ifday?style=flat-square)](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/releases)
[![GitHub commits](https://badgen.net/github/commits/dwightmulcahy/dokuwiki-plugin-ifday/main)](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/commits/main)
[![Issues](https://img.shields.io/github/issues/dwightmulcahy/dokuwiki-plugin-ifday?style=flat-square)](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/issues)
[![Pull Requests](https://img.shields.io/github/issues-pr/dwightmulcahy/dokuwiki-plugin-ifday?style=flat-square)](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/pulls)


## Description

This plugin adds the `<ifday>` syntax block to conditionally render content based on date-related conditions.

It supports:

* Multi-condition logic with `AND`, `OR`, `&&`, `||`
* Comparison operators: `==`, `!=`, `<`, `>`, `<=`, `>=`
* Alias operators: `is` (treated as `==`), `is not` (treated as `!=`)
* Negation operator: `NOT` (treated as logical NOT `!`)
* Parentheses for grouping conditions
* Special date keywords: `weekday`, `weekend`, and `day` (with specific day names like `monday`, `tuesday`, etc.)
* Relative-day keywords: `today`, `tomorrow`, `yesterday`
* Extra aliases: `workday`, `businessday` (same as `weekday`)
* Day name abbreviations (`mon`, `tue`, `wed`, etc.) are supported and normalized
* Boolean checks (e.g., `<ifday weekday>`) without explicit comparisons
* `<else>` block support for `false` condition processing
* Relative offsets: `day+N`, `day-N` (e.g., `day+3` means three days from today)
* Month checks: `month == december`, `month in [jun,jul,aug]`
* Ranges: `day in [mon..wed]`, `month in [dec..feb]`
* Year checks: `year == 2025`
* Boolean shorthand for day-only expressions: `mon or tue`, `not mon`, `(mon and wed) or fri`
* Ordinal weekday-of-month checks: `2nd monday`, `last friday`, `5th tue`, `2nd monday of month`)
* Configurable option to show/hide inline error messages on the wiki page for invalid conditions

---

## Basic Usage

Wrap any content inside `<ifday>` tags with your condition expression:

```dokuwiki
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
</ifday>

<ifday today>
This content appears only today.
</ifday>

<ifday month == december>
This content appears only in December.
</ifday>
```

**Additional examples (new features):**

```dokuwiki
<ifday mon or tue>
Shown on Monday or Tuesday using shorthand.
</ifday>

<ifday not fri>
Hidden on Fridays.
</ifday>

<ifday (mon and wed) or fri>
Complex logic using shorthand-only days.
</ifday>

<ifday 2nd monday>
Visible only on the second Monday of the month.
</ifday>

<ifday last fri>
End-of-month Friday special.
</ifday>
```

---

## `<else>` Block

An optional `<else>` block allows you to specify alternative content to display when the primary condition evaluates to `false`. This removes the need to use a separate `<ifday not ...>` block for simple `true`/`false` scenarios.

If the condition is `true`, the content within the main `ifday` block is rendered. If the condition is `false`, the content within the `<else>` block is rendered instead. If there is no `<else>` block, and the condition is `false`, no content is displayed.

Using the `<else>` block.

```
<ifday weekday>
  The office is open today.
<else>
  The office is closed for the weekend.
</ifday>

<ifday day is "Monday">
  It's the start of the week!
<else>
  It's not Monday.
</ifday>

<ifday (day == "Friday" or day == "Saturday")>
  Happy Hour is starting!
<else>
  It's not time for Happy Hour yet.
</ifday>

<ifday day is Friday>
  Enjoy your weekend!
</ifday>

<ifday 2nd monday>
  Staff meeting today (second Monday).
<else>
  Regular schedule.
</ifday>

<ifday not mon>
  Not Monday content.
<else>
  Monday-only message.
</ifday>
```

---

## Comparison Operators

You can compare `day`, `weekday`, and `weekend` using:

| Operator             | Meaning                                  | Example                              |
| -------------------- | ---------------------------------------- | ------------------------------------ |
| `==` or `is`         | Equals                                   | `day == friday` or `day is friday`   |
| `!=` or `is not`     | Does not equal                           | `day != sunday` or `is not weekday`  |
| `<`, `>`, `<=`, `>=` | Numerical/time comparisons (limited use) | **Not typically used for day names** |

Example:

```dokuwiki
<ifday day != saturday AND day != sunday>
This content appears on all weekdays except weekends.
</ifday>

<ifday today is 2nd monday>
Special notice for the second Monday only.
</ifday>

<ifday today is not last fri>
Any day this month except the final Friday.
</ifday>
```

---

## Logical Operators, Negation, and Grouping

Combine conditions with:

* `AND` or `&&` (logical AND)
* `OR` or `||` (logical OR)
* `NOT` (logical negation, e.g., `NOT weekday`)
* Parentheses `(` and `)` to control evaluation order

Examples:

```dokuwiki
<ifday NOT weekday>
Content is visible only on weekends.
</ifday>

<ifday (weekday AND day != friday) OR weekend>
Content is visible if today is a weekday except Friday, or if it is the weekend.
</ifday>

<ifday (day in [mon..wed]) AND (month in [dec..feb])>
Visible on Monday, Tuesday, or Wednesday only during the winter.
</ifday>

<ifday mon or tue or wed>
Shorthand OR across days.
</ifday>

<ifday (mon and wed) or fri>
Parenthesized shorthand + OR.
</ifday>

<ifday not mon and tue>
True if it’s Tuesday and not Monday (shorthand negation).
</ifday>
```

---

## Boolean Checks Without Explicit Comparisons

Simply using `weekday`, `weekend`, or day name (`monday`, `tuesday`, etc.) without a comparison will evaluate to true or false automatically:

```dokuwiki
<ifday weekday>
Only visible on Monday through Friday.
</ifday>

<ifday weekend>
Only visible on Saturday or Sunday.
</ifday>

<ifday Monday>
Only visible on Monday.
</ifday>

<ifday today>
Only visible today.
</ifday>

<ifday mon, tue>
Comma-separated shorthand list.
</ifday>

<ifday 5th tue>
Only the fifth Tuesday of the month (if it exists).
</ifday>
```

---

## Day Name and Abbreviation Checks

You can check for specific days using full names or 3-letter abbreviations (case-insensitive):

```dokuwiki
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

<ifday not fri>
Not on Friday (uses shorthand negation).
</ifday>

<ifday mon or tue>
Abbreviations combined with shorthand OR.
</ifday>
```

---

## Shorthand Day-Only Syntax

You can simplify conditions that check for a specific day by omitting the full comparison syntax.

For example, these are equivalent:

```dokuwiki
<ifday day is monday>
Content visible only on Mondays.
</ifday>

<ifday monday>
Content visible only on Mondays.
</ifday>

<ifday mon>
Content visible only on Mondays (using 3-letter abbreviation).
</ifday>

<ifday mon or tue>
Two-day check (OR).
</ifday>

<ifday mon and tue>
Both conditions at once (typically false).
</ifday>

<ifday not mon>
Negation in shorthand form.
</ifday>

<ifday (mon and wed) or fri>
Grouping with parentheses.
</ifday>
```

---

## Ordinal Weekday Support (N-th / Last of Month)

Use ordinals to match specific occurrences within the current month:

* `1st`, `2nd`, `3rd`, `4th`, `5th`, or `last` + weekday
* Optional phrase `of month` is accepted: `2nd monday of month`

**Examples:**

```dokuwiki
<ifday 2nd monday>
Second-Monday content.
</ifday>

<ifday last friday>
Month’s final Friday.
</ifday>

<ifday 5th mon>
Only the rare fifth Monday (false in months without one).
</ifday>

<ifday today is 2nd monday>
Shown only when today is the month’s second Monday.
</ifday>

<ifday not last fri>
All days except the final Friday of the month.
</ifday>
```

---

## Day and Month Ranges

You can check if the current day or month falls within a specific range using the `in [...]` syntax. You can use full names, abbreviations, or numbers, and you can combine single items and ranges in a comma-separated list.

### Day Range Examples

```dokuwiki
<ifday day in [mon..fri]>
This content is visible on any weekday.
</ifday>

<ifday day in [sat..tue]>
This is a wrap-around range, visible on Saturday, Sunday, Monday, and Tuesday.
</ifday>

<ifday day in [mon, wed, fri]>
This is visible on Monday, Wednesday, or Friday.
</ifday>
```

### Month Range Examples

```dokuwiki
<ifday month in [jan..mar]>
This is visible during the first quarter of the year.
</ifday>

<ifday month in [dec..feb]>
This is a wrap-around range for winter months.
</ifday>

<ifday month in [may, jun, jul..sep]>
This is visible in May, June, and the third quarter of the year.
</ifday>

<ifday (month in [nov..dec]) AND last fri>
End-of-year Friday promo.
</ifday>

<ifday (month in [jun..aug]) AND (mon or tue)>
Summer content on Mon/Tue, shorthand + range.
</ifday>
```

---

## Mixed Conditions

Combine day names and `weekday`/`weekend` status:

```dokuwiki
<ifday (day == mon OR day == wed) AND weekday>
Visible only on Monday or Wednesday during the weekdays.
</ifday>

<ifday weekend AND day != sun>
Visible on Saturdays only.
</ifday>

<ifday (month in [dec..feb] AND workday)>
Visible on weekdays during winter.
</ifday>

<ifday (year == 2025 AND day in [sat, sun])>
Visible on weekends in the year 2025.
</ifday>

<ifday (tomorrow AND day == friday)>
Visible if tomorrow is Friday.
</ifday>

<ifday (yesterday AND weekday)>
Visible if yesterday was a weekday.
</ifday>

<ifday (2nd monday) AND (month in [jan..mar])>
Q1 content for the second Monday.
</ifday>

<ifday (last fri) OR (mon or tue)>
Either the month’s final Friday, or early-week days.
</ifday>
```

---

## Tier 1 Additions Usage Examples

### Relative-Day Keywords (with useful comparisons)

```dokuwiki
<ifday (tomorrow AND weekday)>
This content appears if tomorrow is a weekday.
</ifday>

<ifday (tomorrow AND day == friday)>
This content appears if tomorrow is Friday.
</ifday>

<ifday (yesterday AND weekend)>
This content appears if yesterday was on a weekend.
</ifday>

<ifday (yesterday AND day == monday)>
This content appears if yesterday was Monday.
</ifday>
```

### Relative Offsets

```dokuwiki
<ifday day+3>
This content shows if three days from today is the current day.
</ifday>

<ifday day-1>
This content shows if yesterday matches.
</ifday>
```

### Month Checks

```dokuwiki
<ifday month == december>
Holiday specials appear only in December.
</ifday>

<ifday month in [jun,jul,aug]>
Summer content (June, July, August).
</ifday>
```

### Year Checks

```dokuwiki
<ifday year == 2025>
This content is only shown during 2025.
</ifday>
```

### Workday / Businessday Aliases

```dokuwiki
<ifday workday>
Visible only on weekdays (Mon–Fri).
</ifday>

<ifday NOT businessday>
Visible only on weekends.
</ifday>
```

**Additional examples (new features):**

```dokuwiki
<ifday (day+1 == mon) AND (not fri)>
Tomorrow is Monday and today is not Friday.
</ifday>

<ifday today is 2nd monday>
Ordinal comparison with `today`.
</ifday>
```

---

## Error Handling and Configuration

* If your condition expression is invalid or unsafe, the plugin logs an error and, by default, **displays a visible warning box** on the page showing the error message and the original condition.
* This visible error display can be toggled on/off in the plugin configuration (`show_errors` option) via the DokuWiki admin interface.
* When disabled, errors will be logged silently without showing messages on the wiki pages.

Example error message style:

```html
<div class="plugin_ifday_error" style="border:1px solid red; padding:10px; color:red; font-weight:bold;">
  ifday plugin error evaluating condition: "day is fridday"
  <br><strong>Details:</strong> Safety check failed for processed condition ...
</div>
```

---

## Summary of Supported Syntax

| Feature           | Syntax Examples                                           | Description                                    |
| ----------------- | --------------------------------------------------------- | ---------------------------------------------- |
| Equality          | `day == monday`, `day is fri`                             | Checks if current day equals specified day     |
| Inequality        | `day != sunday`                                           | Checks if current day is not the specified day |
| Logical AND       | `weekday AND day != friday`, `weekday && day != fri`      | Combine conditions with AND logic              |
| Logical OR        | `weekend OR day == monday`, `weekend or day == mon`       | Combine conditions with OR logic               |
| Negation          | `NOT weekday`, `NOT (day == saturday)`                    | Negates the condition                          |
| Boolean checks    | `weekday`, `weekend`                                      | True if current day is a weekday or weekend    |
| Boolean Day       | `monday`, `tuesday`, `wednesday`, etc                     | True if current day is that day                |
| Grouping          | `(weekday AND day != friday) OR weekend`                  | Use parentheses for complex logic              |
| Day Abbreviations | `day == mon`, `day is tue`                                | Supports 3-letter abbreviations for days       |
| Relative Keywords | `(tomorrow AND day == friday)`, `(yesterday AND weekday)` | Relative-day checks with comparisons           |
| Day Ranges        | `day in [mon..fri]`, `day in [sat, sun, mon]`             | Checks if current day is in a list or range    |
| Month Checks      | `month == december`, `month in [jun..aug]`                | Checks based on calendar month                 |
| Month Ranges      | `month in [1..3]`, `month in [nov..jan, mar]`             | Checks if current month is in a list or range  |
| Year Checks       | `year == 2025`                                            | Checks based on year                           |
| Workday Aliases   | `workday`, `businessday`                                  | Alias for weekday (Mon–Fri)                    |
| Boolean day shorthand    | `mon or tue`, `not mon`, `mon and tue`, `mon, tue` | No need for `day is`; supports `and`, `or`, `not`, commas |
| Ordinal weekday-of-month | `2nd monday`, `last friday`, `5th tue`             | Optional `of month`; works with `today is …`, negation    |

---

## Notes

* The plugin evaluates conditions based on the **server’s current date** and time.
* Logical operators (`AND`, `OR`, `NOT`) and comparison operators (`is`, `==`, `!=`) are **case-insensitive**.
* Parentheses are supported for grouping and clarifying complex logic.
* Invalid or unsafe expressions cause the condition to evaluate as false and produce an error message or log.
* The plugin currently does **not support time-of-day comparisons**, only day/month/year-based logic.
* Day names and abbreviations are normalized internally to ensure flexible matching.

---

## Installation

1. Extract the `ifday` folder into your DokuWiki `lib/plugins/` directory.
2. Make sure the plugin is enabled (usually enabled by default).
3. Use `<ifday>` tags with conditions in your wiki pages as described above.
4. Configure error message visibility from **Admin → Configuration Settings → ifday → Show errors**.

---

## Support

For questions or [issues](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/issues), please contact the plugin author or visit the [DokuWiki forums](https://forum.dokuwiki.org/).
