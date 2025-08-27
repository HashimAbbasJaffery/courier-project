<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8">
  <title>Shipment Status Updated</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <style>
    /* --- Base resets for email clients --- */
    html, body { margin:0 !important; padding:0 !important; height:100% !important; width:100% !important; }
    * { -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; }
    table, td { mso-table-lspace:0pt !important; mso-table-rspace:0pt !important; }
    table { border-spacing:0 !important; border-collapse:collapse !important; table-layout:fixed !important; margin:0 auto !important; }
    img { -ms-interpolation-mode:bicubic; border:0; outline:none; text-decoration:none; height:auto; display:block; }
    a { text-decoration:none; }

    $1
    .btn { display:inline-block; font:600 14px/20px -apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif; padding:12px 18px; border-radius:10px; background:#4f46e5; color:#ffffff !important; border:1px solid #4338ca; }
    .btn:link, .btn:visited { color:#ffffff !important; }

    /* --- Mobile --- */
    @media screen and (max-width: 600px) {
      .container{ width:100% !important; max-width:100% !important; }
      .px-24{ padding-left:16px !important; padding-right:16px !important; }
      .py-24{ padding-top:16px !important; padding-bottom:16px !important; }
      .h1{ font-size:20px !important; line-height:28px !important; }
      .kv td { display:block !important; width:100% !important; }
      .kv td.t-right { text-align:left !important; }
      .pill { display:inline-block !important; padding:6px 10px !important; font-size:11px !important; }
    }

    /* --- Dark mode (supported clients) --- */
    @media (prefers-color-scheme: dark){
      body, .bg-body { background:#0f1420 !important; }
      .card { background:#141b2d !important; border-color:#243047 !important; }
      .text-muted { color:#b6c2e2 !important; }
      .text { color:#e8ecf7 !important; }
      .divider { border-color:#243047 !important; }
    }
  </style>
  <!--[if mso]>
  <style>
    .h1 { font-family: Arial, Helvetica, sans-serif !important; }
  </style>
  <![endif]-->
</head>
<body style="margin:0;padding:0;background:#f4f6fb;" class="bg-body">

  <!-- Preheader (hidden) -->
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
    Shipment {{ $order_id ?? '—' }} status changed to {{ $new_status ?? 'Updated' }}.
  </div>

  <center style="width:100%;background:#f4f6fb;">
    <!--[if mso]>
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td>
    <![endif]-->

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center" style="padding:10px;">
          <table role="presentation" cellpadding="0" cellspacing="0" width="600" class="container" style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;box-shadow:0 10px 30px rgba(17, 24, 39, 0.08);">

            <!-- Header -->
            <tr>
              <td class="px-24 py-24" style="padding:24px 24px 8px 24px;border-bottom:1px solid #eef1f6;">
                <table role="presentation" width="100%">
                  <tr>
                    <td align="left">
                      <span class="text" style="font:700 18px/1.2 -apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif;color:#0b1220;">
                        Shipment Update
                      </span>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <!-- CTA: Shipper Advice -->


            <!-- Card: Shipment summary -->
            <tr>
              <td style="padding:16px 10px 24px 10px;">
                <table role="presentation" width="100%" class="card">
                  <tr>
                    <td style="padding:16px 16px 0 16px;">
                      <div class="text" style="font:600 14px/20px -apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif;">Shipment Details</div>
                    </td>
                  </tr>
                  <tr>
                    <td style="padding:8px 10px 16px 10px;">
                      <table role="presentation" width="100%" class="kv" style="font:400 14px/22px -apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif;color:#0b1220;">

                        <tr>
                          <td style="padding:8px 0;color:#6b7280;">Tracking Number</td>
                          <td class="t-right" style="padding:8px 0;text-align:right;">{{ $tracking_number ?? '—' }}</td>
                        </tr>

                        <tr>
                          <td style="padding:8px 0;color:#6b7280;">Order ID</td>
                          <td class="t-right" style="padding:8px 0;text-align:right;">#{{ $order_id ?? '—' }}</td>
                        </tr>

                        <tr>
                          <td style="padding:8px 0;color:#6b7280;">Pickup Date</td>
                          <td class="t-right" style="padding:8px 0;text-align:right;">{{ $pickup_date ?? 'Not picked yet' }}</td>
                        </tr>

                        <tr>
                          <td style="padding:8px 0;color:#6b7280;width:40%;">Costumer Name</td>
                          <td class="t-right" style="padding:8px 0;text-align:right;">{{ $consignee_name ?? '—' }}</td>
                        </tr>
                        <tr>
                          <td style="padding:8px 0;color:#6b7280;">Customer No.</td>
                          <td class="t-right" style="padding:8px 0;text-align:right;">{{ $customer_number ?? '—' }}</td>
                        </tr>

                        <tr>
                          <td style="padding:8px 0;color:#6b7280;">Delivery City</td>
                          <td class="t-right" style="padding:8px 0;text-align:right;">{{ $delivery_city ?? 'Karachi' }}</td>
                        </tr>

                        <tr>
                          <td style="padding:8px 0;color:#6b7280;">COD Amount</td>
                          <td class="t-right" style="padding:8px 0;text-align:right;">{{ number_format($cod_amount ?? 0, 0) }}</td>
                        </tr>
                        <tr>
                          <td style="padding:8px 0;color:#6b7280;">Status</td>
                          <td class="t-right" style="padding:8px 0;text-align:right;">
                            <span style="display:inline-block;padding:6px 10px;border-radius:999px;background:#ecfdf5;color:#059669;border:1px solid #bbf7d0;font-weight:600; font-size: 10px;">{{ $status ?? 'Updated' }}</span>
                          </td>
                        </tr>

                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>

    <tr>
              <td class="px-24" align="center" style="padding:16px 0px 0 0px; padding-bottom: 10px;">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="https://ecom.leopardscourier.com/merchant/booking/shipper_advice" style="height:40px;v-text-anchor:middle;width:260px;" arcsize="20%" strokecolor="#4338ca" fillcolor="#4f46e5">
                  <w:anchorlock/>
                  <center style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:600;">View Shipper Advice</center>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-- -->
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td align="center" bgcolor="#4f46e5" style="border-radius:10px;">
                      <a href="https://ecom.leopardscourier.com/merchant/booking/shipper_advice" class="btn" target="_blank" rel="noopener" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#4f46e5;color:#ffffff !important;border:1px solid #4338ca;">View Shipper Advice</a>
                    </td>
                  </tr>
                </table>
                <!--<![endif]-->
              </td>
            </tr>

    <!--[if mso]>
    </td></tr></table>
    <![endif]-->
  </center>
</body>
</html>
