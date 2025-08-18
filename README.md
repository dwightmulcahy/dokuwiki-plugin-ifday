# ifday Plugin for DokuWiki

[![PR Check](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/actions/workflows/pr-check.yml/badge.svg)](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/actions/workflows/pr-check.yml)

## Description
This plugin adds the `<ifday>` syntax block to conditionally render content based on date-related conditions.

It supports:
- Multi-condition logic with `AND`, `OR`, `&&`, `||`
- Comparison operators: `==`, `!=`, `<`, `>`, `<=`, `>=`
- Alias operators: `is` (treated as `==`), `is not` (treated as `!=`)
- Negation operator: `NOT` (treated as logical NOT `!`)
- Parentheses for grouping conditions
- Special date keywords: `weekday`, `weekend`, and `day` (with specific day names like `monday`, `tuesday`, etc.)
- Relative-day keywords: `today`, `tomorrow`, `yesterday`
- Extra aliases: `workday`, `businessday` (same as `weekday`)
- Day name abbreviations (`mon`, `tue`, `wed`, etc.) are supported and normalized
- Boolean checks (e.g., `<ifday weekday>`) without explicit comparisons
- `<else>` block support for `false` condition processing
- Relative offsets: `day+N`, `day-N` (e.g., `day+3` means three days from today)
- Month checks: `month == december`, `month in [jun,jul,aug]`
- Year checks: `year == 2025`
- Configurable option to show/hide inline error messages on the wiki page for invalid conditions

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
```

---

## Comparison Operators
You can compare `day`, `weekday`, and `weekend` using:

| Operator             | Meaning                                  | Example                              |
|----------------------| ---------------------------------------- |--------------------------------------|
| `==` or `is`         | Equals                                   | `day == friday` or `day is friday`   |
| `!=` or `is not`     | Does not equal                          | `day != sunday` or `is not weekday`  |
| `<`, `>`, `<=`, `>=` | Numerical/time comparisons (limited use) | **Not typically used for day names** |

Example:

```dokuwiki
<ifday day != saturday AND day != sunday>
This content appears on all weekdays except weekends.
</ifday>
```

---

## Logical Operators, Negation, and Grouping
Combine conditions with:

- `AND` or `&&` (logical AND)
- `OR` or `||` (logical OR)
- `NOT` (logical negation, e.g., `NOT weekday`)
- Parentheses `(` and `)` to control evaluation order

Examples:

```dokuwiki
<ifday NOT weekday>
Content is visible only on weekends.
</ifday>

<ifday (weekday AND day != friday) OR weekend>
Content is visible if today is a weekday except Friday, or if it is the weekend.
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

<ifday (month == december AND workday)>
Visible on weekdays in December only.
</ifday>

<ifday (year == 2025 AND weekend)>
Visible on weekends in the year 2025.
</ifday>

<ifday (tomorrow AND day == friday)>
Visible if tomorrow is Friday.
</ifday>

<ifday (yesterday AND weekday)>
Visible if yesterday was a weekday.
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

---

## Error Handling and Configuration
- If your condition expression is invalid or unsafe, the plugin logs an error and, by default, **displays a visible warning box** on the page showing the error message and the original condition.
- This visible error display can be toggled on/off in the plugin configuration (`show_errors` option) via the DokuWiki admin interface.
- When disabled, errors will be logged silently without showing messages on the wiki pages.

Example error message style:

```html
<div class="plugin_ifday_error" style="border:1px solid red; padding:10px; color:red; font-weight:bold;">
  ifday plugin error evaluating condition: "day is fridday"
  <br><strong>Details:</strong> Safety check failed for processed condition ...
</div>
```

---

## Summary of Supported Syntax

| Feature             | Syntax Examples                                          | Description                                      |
|---------------------|----------------------------------------------------------|--------------------------------------------------|
| Equality            | `day == monday`, `day is fri`                            | Checks if current day equals specified day       |
| Inequality          | `day != sunday`                                          | Checks if current day is not the specified day   |
| Logical AND         | `weekday AND day != friday`, `weekday && day != fri`     | Combine conditions with AND logic                |
| Logical OR          | `weekend OR day == monday`, `weekend or day == mon`      | Combine conditions with OR logic                 |
| Negation            | `NOT weekday`, `NOT (day == saturday)`                   | Negates the condition                            |
| Boolean checks      | `weekday`, `weekend`                                     | True if current day is a weekday or weekend      |
| Boolean Day         | `monday`, `tuesday`, `wednesday`, etc                    | True if current day is that day                  |
| Grouping            | `(weekday AND day != friday) OR weekend`                 | Use parentheses for complex logic                |
| Day Abbreviations   | `day == mon`, `day is tue`                               | Supports 3-letter abbreviations for days         |
| Relative Keywords   | `(tomorrow AND day == friday)`, `(yesterday AND weekday)` | Relative-day checks with comparisons             |
| Month Checks        | `month == december`, `month in [jun,jul,aug]`            | Checks based on calendar month                   |
| Year Checks         | `year == 2025`                                           | Checks based on year                             |
| Workday Aliases     | `workday`, `businessday`                                 | Alias for weekday (Mon–Fri)                      |

---

## Notes
- The plugin evaluates conditions based on the **server’s current date** and time.
- Logical operators (`AND`, `OR`, `NOT`) and comparison operators (`is`, `==`, `!=`) are **case-insensitive**.
- Parentheses are supported for grouping and clarifying complex logic.
- Invalid or unsafe expressions cause the condition to evaluate as false and produce an error message or log.
- The plugin currently does **not support time-of-day comparisons**, only day/month/year-based logic.
- Day names and abbreviations are normalized internally to ensure flexible matching.

---

## Installation
1. Extract the `ifday` folder into your DokuWiki `lib/plugins/` directory.
2. Make sure the plugin is enabled (usually enabled by default).
3. Use `<ifday>` tags with conditions in your wiki pages as described above.
4. Configure error message visibility from **Admin → Configuration Settings → ifday → Show errors**.

---

## Support
For questions or [issues](https://github.com/dwightmulcahy/dokuwiki-plugin-ifday/issues), please contact the plugin author or visit the [DokuWiki forums](https://forum.dokuwiki.org/).
