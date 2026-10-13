<?php

return [

    // Letterhead printed on purchase orders
    'company' => [
        'name'     => 'MGRC Biopharma Sdn Bhd',
        'legal'    => '',   // company registration no. (optional, add when known)
        'address'  => "8F, Jalan Teknologi 3/6, Taman Sains Selangor 1,\nPJU 5 Kota Damansara, 47810 Petaling Jaya,\nSelangor, Malaysia",
        'phone'    => '03-7890 0015',
        'email'    => 'joana@mgrc.com.my',
    ],

    // Where suppliers should deliver to
    'delivery_address' => "MGRC Biopharma Sdn Bhd\n8F, Jalan Teknologi 3/6, Taman Sains Selangor 1,\nPJU 5 Kota Damansara, 47810 Petaling Jaya,\nSelangor, Malaysia",

    // Logo: PNG at public/images/po-logo.png
    'po_logo' => 'images/po-logo.png',

    // Currencies allowed on suppliers and POs (ISO codes)
    'currencies' => [
        'MYR' => 'MYR - Malaysian Ringgit',
        'USD' => 'USD - US Dollar',
        'CNY' => 'CNY - Chinese Renminbi (RMB)',
    ],
];