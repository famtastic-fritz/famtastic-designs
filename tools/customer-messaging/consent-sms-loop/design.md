# Consent SMS loop design

This is an operational backend contract, not a public visual component. Consumer UIs should show one plain state per message: waiting, handed to provider, sent, delivered when reported, failed, or needs review. Owner controls must show consent, STOP, current appointment and reversal before any manual retry. The pilot screen, if built, must say "Fritz-only test" and never list customer contacts.

The package is provider-neutral at the business boundary. Textbee is the technical lab transport; a compliant business transport can replace it without changing booking authority or consent records. No client application calls FAMtastic's runtime to decide appointment truth.
