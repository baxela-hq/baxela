# Baxela Design Tokens — extracted from thumbnails (500px q80)

## Global palette
- Background: white (#ffffff)
- Primary action: near-black (#171717–#1a1a1a range) buttons, white label text
- Secondary text: gray (~#6b7280)
- Input borders: light gray (~#d1d5db / #e5e7eb), radius ~8px
- Accent (success/links): green (~#16a34a) — seen on "show" password toggle in signup

## Auth screens (01–05)
- Layout: split-screen, left full-bleed photo panel (845×1024 of 1440×1024), white Logo (143×58) @(60,60) over photo; right column starts x≈895, content width 445px.
- Input component: 445×56 (+label = 445×80), light-gray 8px border, leading icon, small gray label above.
- Button: 445×56, near-black bg, white text, radius ~8px.
- Links (forgot password / signup): green or underlined gray text, right/left aligned in row with checkbox.

### 01 Login (`3015:8240`)
- "Login Details" heading (~24–28px, semibold, near-black)
- 2 stacked inputs (Email, Password w/ "show" toggle), remember-me checkbox 150×24 + "Forgot Password?" link row, full-width dark button.

### 02 Signup (`3257:6521`)
- "Signup Details" heading @(895,215), 445×595 block; form `3257:6571`: 4 labeled inputs (name — person icon, email — mail icon, password + confirm w/ green "show" text), full-width dark button below; terms text likely under button.

### 03 Forgot Password (`3015:8241`)
- CENTERED single column (~445px), no photo panel; Logo top center; "Forgot Password?" heading; gray helper text ("enter the email address, we will send an OTP code"); 1 email input (mail icon); full-width dark "Send Code" button.

## Pending screens
- 04 enter-otp, 05 login-successful, 06 home (view ALONE, 88KB), 07 mega-menu, 08/09 listing, 10/11/12 PDP, 14 checkout.

## 04 Enter OTP (`design/thumbs/04-enter-otp.jpg`, node 3015:8242)
- ⚠️ ANOMALY: thumbnail shows a split layout IDENTICAL to 01-login (photo panel left; "Login Details" form right: Email address, Password + green "show", Remember me + green "Forgot Password?" link, full-width near-black "Login" button).
- NO OTP digit boxes visible → likely mis-capture; node may duplicate login visuals. VERIFY: crop form region of design/screens/04-enter-otp.png (right half, x≈760..1440) → 500px thumb → re-view.

## 05 Login Successful (`design/thumbs/05-login-successful.jpg`, node 3015:8243)
- Split layout: left ~60% illustration (person carrying large mustard/olive shopping bag, soft neutral/mint backdrop), right white panel.
- Right panel: small centered text stack (heading + helper lines + likely confirmation button) — text unreadable at 500px. VERIFY: crop right panel (x≈600..1440) → 500px thumb → re-view for exact copy.

### 04-verify-form.jpg (crop zoom verification)
RESOLVED: node 3015:8242 is a REAL OTP screen (earlier 500px thumb was misleading).
- Heading: "Verify Your Identity" (centered in form column)
- Helper: "Please enter the verification code sent to your email address."
- Single labeled input "Verification Code" (one full-width field, NOT 6 digit boxes)
- Green "Resend Code" link below input
- Full-width near-black "Verify" button
Layout family: single centered column like 03-forgot-password (no photo panel visible in form region).
