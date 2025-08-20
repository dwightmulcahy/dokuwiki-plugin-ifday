<?php
declare(strict_types=1);

/**
 * rendered.php
 * - Rendered Content tests (ALL days)
 * - Return an array of [condition, ifContent, elseContent]
 */
return [
    ['day == monday', "It's the start of the work week.", ''],
    ['day != fri && not weekend', "The weekend is not here yet.", ''],
    ['weekend', "Enjoy your time off!", "Time to get to work."],
    ['is weekday', "It's a weekday, party over. 😩", "It's the weekend, time to party! 🎉"],
    ['weekend AND day == sunday', "It's the last day of the weekend.", ''],
    ['day is saturday OR day is sunday', "It's a weekend day.", ''],
    ['day == mon || day == tue AND weekend', "This will be true only Monday.", ''],
    ['NOT day == sunday', "It's not Sunday.", "Sunday-Funday! 🍺🍺🍺🍺"],
    ['is not weekend', "It's not the weekend.", '']
];
