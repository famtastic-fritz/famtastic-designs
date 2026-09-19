import { CREDIT_LOGO, CREDIT_NAME, CREDIT_HREF, creditRowStyle, creditLinkStyle, creditImageStyle, creditCss } from '../lib/creatorCredit.js';

export default function CreatorCredit() {
  return <><style>{creditCss}</style><div data-famtastic-creator-credit="v1" style={creditRowStyle}>
    <a href={CREDIT_HREF} aria-label={CREDIT_NAME} referrerPolicy="no-referrer" style={creditLinkStyle}>
      <img src={CREDIT_LOGO} alt={CREDIT_NAME} width="2172" height="724" style={creditImageStyle} />
    </a>
  </div></>;
}
