<?php

return [

    // Letterhead printed on purchase orders.
    // Confirm with RHYTHM / MGRC procurement whose details go here.
    'company' => [
        'name'     => 'RHYTHM',
        'legal'    => 'Company Sdn Bhd (Reg. No. 000000-X)',
        'address'  => "Address line 1\nAddress line 2\nKuala Lumpur, Malaysia",
        'phone'    => '+60 3-0000 0000',
        'email'    => 'rhythm@example.com',
    ],

    // Where suppliers should deliver to
    'delivery_address' => "RHYTHM c/o MGRC\nAddress line 1\nKuala Lumpur, Malaysia",

    // Optional logo: put a PNG at public/images/po-logo.png
    'po_logo' => 'images/po-logo.png',
];