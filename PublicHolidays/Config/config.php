<?php

return [
    'name' => 'Public Holidays',

    // Default cantons observed by the office (drives the "we are closed today" logic).
    // CH-JU = Canton of Jura. Can be changed in the admin settings.
    'default_cantons' => ['CH-JU'],

    // Days ahead scanned when looking for the "next working day".
    'next_working_day_lookahead' => 30,
];
