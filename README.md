1&1 ipayment
============

1&1 ipayment is fully integrated in the Magento Onepage and Multishipping checkout and offers credit card payment and direct debit (ELV) for German and Austrian merchants.

Highlights:
-----------
- Seamlessly integrated in the checkout
- PCI compliant credit card payment
- Support for 3D-Secure payments (not available in MultiShipping checkout for technical reasons)
- Payment capturing, refunding and canceling in the Magento backend - Includes a demo account ready for testing
- No setup fee for Magento merchants. Click here for registration.

Upcoming features:
------------------
- Backend CC payment
- Address check

 

This extension has been developed by PHOENIX MEDIA, Magento Gold Partner in Stuttgart and Vienna.

Note: This module requires the PHP SOAP and OpenSSL extensions to be installed.

 

Changelog
---------

From version 1.4.1 on all changes are listed in the "Release Notes" tab.

1.4.0:
Attention: This version doesn't support Magento 1.3 branch anymore. Use an older release if you use Magento < 1.4. - Removed templates in frontend/default/default - fixed issue with non standard characters in address

1.3.2:
- Added base template
- fixed smaller issues 

1.3.0:
- Moved payment form in an iFrame (check your templates!)
- Support for ELV payments with Austrian bank account
- Support for UPAY
- Initial support for multiple captures (beta)
- Added min/max order values in configuration
- Made invoice text configurable
- Added test mode option to deactivate security checks for development
- Added debug option to deactivate logging
- Moved log information in separte file
- Tested module with latest Magento 1.5.x branch 

1.2.13:
- Fixed JavaScript error in IE 

1.2.12:
- Fixed 1 cent rounding issue 

1.2.11:
- Fixed issue with shopping card reload on cancelation (Magento 1.4.1)
- Code cleanup 

1.2.10:
- Minor fix for Magento 1.4.1 

1.2.9:
- BC compatibility fix for 3D secure payments
- small improvements for Magento 1.4.1 

1.2.8:
- Small fix for EE 1.8 compatibility 

1.2.7:
- Prevent falsely canceled orders (back button issue)
- Fixed capture issue on 3D secure payments

1.2.6:
- Added missing file

1.2.5:
- Cleaned up 3D-Secure processing

1.2.4:
ATTENTION: 3D-Secure payment module is removed. Copy account information to normal credit card module.
- 3D-Secure functionality (still beta) is moved in standard credit card module
- Made 3D-Secure process more communicative and improved error handling
- Enhanced CVV handling for smaller credit card types
- Fixed several issues in checkout

1.2.3:
- Revert change in Multishipping Checkout
- Code cleanup

1.2.2:
- Added missing translations
- Fixed saving of transaction ID on 3D-Secure payments
- Cleanup

1.2.1:
- several fixes for 3D-Secure payments

1.2.0:
- Support for 3D-Secure payments

1.1.0:
- ELV support
- Multishipping support
- Adds new JS API
- JS improvements
- Support for multiple application IDs
- Several minor fixes and enhancements