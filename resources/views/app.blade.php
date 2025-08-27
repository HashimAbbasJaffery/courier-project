<!DOCTYPE html>

<html lang="en" class="light-style layout-navbar-fixed layout-menu-fixed layout-compact " dir="ltr"
    data-theme="theme-bordered" data-assets-path="/Leopard_courier/assets/" data-template="vertical-menu-template-bordered"
    data-style="light">


<!-- Mirrored from demos.themeselection.com/materio-bootstrap-html-admin-template/html/vertical-menu-template-bordered/form-layouts-vertical.html by HTTrack Website Copier/3.x [XR&CO'2014], Wed, 01 May 2024 13:20:12 GMT -->

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Booking</title>


    <meta name="description" content="Start your development with a Dashboard for Bootstrap 5" />
    <meta name="keywords"
        content="dashboard, material, material design, bootstrap 5 dashboard, bootstrap 5 design, bootstrap 5">
    <!-- Canonical SEO -->
    <link rel="canonical" href="https://themeselection.com/item/materio-bootstrap-html-admin-template/">


    <!-- ? PROD Only: Google Tag Manager (Default ThemeSelection: GTM-5DDHKGP, PixInvent: GTM-5J3LMKC) -->
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                '../../../../www.googletagmanager.com/gtm5445.html?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-5DDHKGP');
    </script>
    <!-- End Google Tag Manager -->

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon"
        href="https://demos.themeselection.com/materio-bootstrap-html-admin-template/assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;ampdisplay=swap"
        rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/remixicon/remixicon.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/flag-icons.css') }}" />

    <!-- Menu waves for no-customizer fix -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-bordered.css') }}"
        class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/typeahead-js/typeahead.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
    <link rel="stylesheet"
        href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}" />
    <link rel="stylesheet"
        href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.css') }}" />
    <link rel="stylesheet"
        href="{{ asset('assets/vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/jquery-timepicker/jquery-timepicker.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/pickr/pickr-themes.css') }}" />


    <!-- Include JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>


    <!-- Page CSS -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Helpers -->
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    <!-- <script src="{{ asset('assets/vendor/js/template-customizer.js') }}"></script> -->

    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{ asset('assets/js/config.js') }}"></script>

    <!-- In header.php -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://kit.fontawesome.com/231b67747d.js" crossorigin="anonymous"></script>



    <style>


        /* Dropdown container */
        .multiselect__content-wrapper {
            position: absolute;
            width: 100%;
            max-height: 250px;
            overflow-y: auto;
            background: #ffffff;
            /* White background */
            border: 1px solid #cbd5e1;
            /* Soft gray border */
            border-radius: 5px;
            box-shadow: 0 2px 6px rgba(100, 116, 139, 0.1);
            /* subtle shadow */
            z-index: 1000;
            box-sizing: border-box;
        }

        /* Each option */
        .multiselect__option {
            padding: 10px 15px;
            font-size: 14px;
            color: #374151;
            /* Medium dark gray */
            cursor: pointer;
            width: 100%;
            /* Full width */
            box-sizing: border-box;
            transition: background-color 0.15s ease;
        }

        /* Hover effect */
        .multiselect__option--highlight {
            background-color: #f3f4f6;
            /* Very light gray */
            color: #1f2937;
            width: 100%;
            /* Darker gray */
        }

        /* Selected option styling */
        .multiselect__option--selected {
            background-color: #e5e7eb;
            /* Light gray */
            font-weight: 600;
            width: 100%;
            /* Ensure selected option fills width */
            color: #111827;
            /* Darker text for selected */
        }

        /* Disabled option */
        .multiselect__option--disabled {
            color: #9ca3af;
            cursor: not-allowed;
        }

        /* Scrollbar for Webkit browsers */
        .multiselect__content-wrapper::-webkit-scrollbar {
            width: 7px;
        }


        .multiselect__content-wrapper::-webkit-scrollbar-track {
            background: #f9fafb;
            border-radius: 5px;
        }

        .multiselect__content-wrapper::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 5px;
            border: 2px solid #f9fafb;
        }
    </style>

    @routes
    @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
    @inertiaHead


</head>
<style>



    body {
        background: #f3f4f9;
    }
    #layout-menu,
    .layout-navbar {
        background: #f3f4f9 !important;
    }
    .card-header {
        padding-left: 0px;
    }
    .card {
        padding-left: 20px !important;
    }
    .card {
        box-shadow: 0px 0px 5px 0px rgba(0,0,0,0.11);
-webkit-box-shadow: 0px 0px 5px 0px rgba(0,0,0,0.11);
-moz-box-shadow: 0px 0px 5px 0px rgba(0,0,0,0.11);
    }
    .menu-vertical .menu-item .menu-link {
        text-decoration: none;

    }

    .dataTables_wrapper .row {
        padding: 15px 25px;
    }

    .dataTables_wrapper .dt-row {
        padding: 0 !important;
    }

    .text-nowrap {
        white-space: wrap !important;
    }

    .loader {
        width: 48px;
        height: 48px;
        border: 5px solid #FFF;
        border-bottom-color: transparent;
        border-radius: 50%;
        display: inline-block;
        box-sizing: border-box;
        animation: rotation 1s linear infinite;
    }

    .loader.small {
        width: 20px;
        height: 20px;
    }

    @keyframes rotation {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>

<body>


    <!-- ?PROD Only: Google Tag Manager (noscript) (Default ThemeSelection: GTM-5DDHKGP, PixInvent: GTM-5J3LMKC) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5DDHKGP" height="0" width="0"
            style="display: none; visibility: hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <!-- Layout wrapper -->
    @inertia
    <!-- / Layout wrapper -->
    



    <!-- <div class="buy-now">
    <a href="https://themeselection.com/item/materio-bootstrap-html-admin-template/" target="_blank" class="btn btn-danger btn-buy-now">Buy Now</a>
  </div>
   -->
    <script>
        function loadPage(pageUrl) {
            $.ajax({
                url: pageUrl,
                type: 'GET',
                success: function(data) {
                    $('#content').html(data);
                },
                error: function(xhr) {
                    $('#content').html('<p style="color:red;">Error loading page.</p>');
                }
            });
        }
    </script>

    <!--/ Scrollable -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <!-- <script src="/Leopard_courier/assets/vendor/libs/jquery/jquery.js"></script> -->
    <!-- Core JS -->
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/hammer/hammer.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/i18n/i18n.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/jquery-timepicker/jquery-timepicker.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <!-- Page JS -->
    <script src="{{ asset('assets/js/form-layouts.js') }}"></script>
    <script src="{{ asset('assets/js/tables-datatables-basic.js') }}"></script>
    <script src="{{ asset('assets/js/forms-pickers.js') }}"></script>


</body>
<script>
    $(document).ready(function() {
        $('#myTable').DataTable({
            "pageLength": 10,
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ]
        });

    });
</script>

<!-- Mirrored from demos.themeselection.com/materio-bootstrap-html-admin-template/html/vertical-menu-template-bordered/form-layouts-vertical.html by HTTrack Website Copier/3.x [XR&CO'2014], Wed, 01 May 2024 13:20:12 GMT -->

</html>

<!-- beautify ignore:end -->
