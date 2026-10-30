import sys
import json
import time
import re
import os
from pathlib import Path
from selenium import webdriver
from selenium.common.exceptions import SessionNotCreatedException
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC


def create_chrome(options):
    # Container uses matched Chromium and driver from the same Debian repository.
    if os.environ.get('CHROME_BIN'):
        options.binary_location = os.environ['CHROME_BIN']
    if os.environ.get('CHROMEDRIVER_BIN'):
        return webdriver.Chrome(service=Service(os.environ['CHROMEDRIVER_BIN']), options=options)
    # Reuse an installed driver before asking Selenium Manager to download one.
    # An explicit Selenium driver setting still takes precedence.
    if not os.environ.get('SE_CHROMEDRIVER'):
        executable = 'chromedriver.exe' if os.name == 'nt' else 'chromedriver'
        roots = [Path(__file__).resolve().parents[2] / 'chromedriver',
                 Path.home() / '.cache' / 'selenium' / 'chromedriver']
        candidates = sorted((path for root in roots for path in root.glob('**/' + executable)),
                            key=lambda path: tuple(int(n) for n in re.findall(r'\d+', path.parent.name)),
                            reverse=True)
        if candidates:
            try:
                return webdriver.Chrome(service=Service(str(candidates[0])), options=options)
            except SessionNotCreatedException as error:
                # Let Selenium resolve a new driver after a browser version update.
                if 'only supports Chrome version' not in str(error):
                    raise
    return webdriver.Chrome(options=options)

def clean_unicode_icons(text):
    if not text:
        return ""
    # Remove private use unicode icons (PUA) and zero-width spaces
    cleaned = re.sub(r'[\ue000-\uf8ff\u200b-\u200f\ufeff]', '', text)
    return cleaned.strip()

def clean_shop_name(name):
    if not name:
        return "Bisnis Tanpa Nama"
    name = clean_unicode_icons(name).strip()

    # If all-caps and longer than 15 chars, convert to title case preserving common acronyms
    if name.isupper() and len(name) > 15:
        words = name.split()
        acronyms = {'PT', 'CV', 'UD', 'AHASS', 'BCA', 'BRI', 'BNI', 'MANDIRI', 'BSI', 'KFC', 'J&T', 'JNE', 'SiCepat'}
        title_words = [w if w in acronyms else w.capitalize() for w in words]
        name = " ".join(title_words)

    # Remove redundant trailing slogans or double separators
    name = re.sub(r'\s*\|\s*', ' - ', name)
    name = re.sub(r'\s{2,}', ' ', name).strip()
    return name

def clean_address(addr, location=""):
    if not addr:
        return f"{location}, Indonesia" if location else "Indonesia"

    addr = clean_unicode_icons(addr).strip()

    # Strip opening hours prefix if present e.g. "Buka · Tutup pukul 21.00 · "
    addr = re.sub(r'^(Buka|Tutup|Buka 24 jam|24 jam|Setiap hari)[^·\n]*·\s*', '', addr, flags=re.IGNORECASE)

    # Strip category prefix e.g. "Bengkel Sepeda Motor · " or "Laundry · "
    addr = re.sub(r'^[A-Za-z\s]+·\s*', '', addr)

    # Strip leading direction words like "Timur, ", "Barat, ", "Selatan, ", "Utara, "
    addr = re.sub(r'^(Timur|Barat|Selatan|Utara|Pusat),\s*', '', addr, flags=re.IGNORECASE)

    # Deduplicate repeated phrases like 'Jl. KH. Agus Salim Jl. KH. Agus Salim'
    parts = addr.split(',')
    clean_parts = []
    for part in parts:
        part = part.strip()
        match = re.search(r'(.{6,40})\s+\1', part)
        if match:
            part = part.replace(match.group(0), match.group(1))
        clean_parts.append(part)
    addr = ', '.join(p for p in clean_parts if p)

    match = re.search(r'(.{8,40})\s+\1', addr)
    if match:
        addr = addr.replace(match.group(0), match.group(1))

    # Clean up double dots or commas
    addr = re.sub(r',\s*,', ',', addr)
    addr = re.sub(r'\s{2,}', ' ', addr).strip(' ,·')

    # If address is too short or doesn't mention location, append location
    if location and location.lower() not in addr.lower() and len(addr) < 40:
        addr = f"{addr}, {location}"

    return addr

def extract_clean_phone(text):
    if not text:
        return ""
    # Match Indonesian mobile & landline numbers, including area codes like "(0361) 123456"
    m = re.search(r'(?:\+62|\(0|0)[0-9\s()-]{8,18}', text)
    if m:
        cleaned = m.group(0).strip()
        digits = re.sub(r'\D', '', cleaned)
        if len(digits) >= 9:
            # Format nicely as 08xx-xxxx-xxxx
            if digits.startswith('62'):
                digits = '0' + digits[2:]
            if len(digits) >= 10:
                if len(digits) == 10:
                    return f"{digits[:4]}-{digits[4:7]}-{digits[7:]}"
                elif len(digits) == 11:
                    return f"{digits[:4]}-{digits[4:7]}-{digits[7:]}"
                else:
                    return f"{digits[:4]}-{digits[4:8]}-{digits[8:]}"
            return cleaned
    return ""

def format_whatsapp_number(phone_str):
    if not phone_str:
        return ""
    digits = re.sub(r'\D', '', phone_str)
    if digits.startswith('0'):
        return '62' + digits[1:]
    elif digits.startswith('8'):
        return '62' + digits
    elif digits.startswith('62'):
        return digits
    return digits

def read_weekly_hours(driver):
    days = r'(Senin|Selasa|Rabu|Kamis|Jumat|Sabtu|Minggu|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)'
    def read_rows():
        rows = []
        seen = set()
        for row in driver.find_elements(By.CSS_SELECTOR, '[role="main"] table tr'):
            cells = row.find_elements(By.CSS_SELECTOR, 'td')
            if len(cells) < 2:
                continue
            day = clean_unicode_icons(cells[0].text)
            value = clean_unicode_icons(cells[1].get_attribute('aria-label') or cells[1].text)
            match = re.fullmatch(days, day, re.IGNORECASE)
            if match and value and day.lower() not in seen:
                seen.add(day.lower())
                rows.append(f'{day}: {value}')
        return '\n'.join(rows) if len(rows) == 7 else None
    schedule = read_rows()
    if schedule:
        return schedule
    selector = '[role="main"] [data-item-id="oh"], [role="main"] [aria-expanded][jsaction*="openhours"], [role="main"] [aria-label][aria-expanded]'
    buttons = WebDriverWait(driver, 12).until(lambda _: driver.find_elements(By.CSS_SELECTOR, selector))
    for button in buttons:
        label = (button.get_attribute('aria-label') or '').lower()
        if button.get_attribute('data-item-id') == 'oh' or 'openhours' in (button.get_attribute('jsaction') or '') or any(word in label for word in ['buka', 'tutup', 'hours', 'open', 'closed']):
            if button.get_attribute('aria-expanded') != 'true':
                button.click()
            break
    return WebDriverWait(driver, 20).until(lambda _: read_rows())

def scrape_hours(url):
    from urllib.parse import urlparse, parse_qsl, urlencode, urlunparse, unquote
    parts = urlparse(url)
    if parts.scheme != 'https' or parts.hostname not in ('google.com', 'www.google.com', 'maps.google.com'):
        raise ValueError('Invalid Maps URL')
    query = dict(parse_qsl(parts.query))
    query['hl'] = 'id'
    url = urlunparse(parts._replace(query=urlencode(query)))
    identity = re.search(r'!1s0x[0-9a-f]+:(0x[0-9a-f]+)', unquote(url), re.IGNORECASE)
    if identity:
        # Search-card links can carry a reduced detail payload. Open the place
        # by its stable CID to request the full business panel instead.
        url = f'https://www.google.com/maps?cid={int(identity.group(1), 16)}&hl=id'
    options = Options()
    for arg in ['--headless=new', '--disable-gpu', '--no-sandbox', '--lang=id', '--window-size=1280,1000']:
        options.add_argument(arg)
    driver = create_chrome(options)
    try:
        driver.set_page_load_timeout(30)
        driver.get(url)
        for button in driver.find_elements(By.XPATH, "//button[contains(., 'Accept all') or contains(., 'Terima semua') or contains(., 'Setuju')]"):
            button.click()
            break
        WebDriverWait(driver, 15).until(EC.presence_of_element_located((By.CSS_SELECTOR, '[role="main"] h1')))
        hours = read_weekly_hours(driver)
        return [{'gmap_url': driver.current_url, 'opening_hours': hours}] if hours else []
    finally:
        driver.quit()

def read_place_contact(driver, url, deadline):
    """Open a place's detail panel and read the phone and website that list cards often omit."""
    driver.get(url)
    wait = max(1, min(8, deadline - time.monotonic()))
    selectors = '[role="main"] [data-item-id^="phone:tel:"], [role="main"] [data-item-id="address"]'
    try:
        WebDriverWait(driver, wait).until(lambda d: d.find_elements(By.CSS_SELECTOR, selectors))
    except Exception:
        return "", ""
    phone = ""
    for el in driver.find_elements(By.CSS_SELECTOR, '[role="main"] [data-item-id^="phone:tel:"]'):
        digits = re.sub(r'\D', '', el.get_attribute('data-item-id') or '')
        if digits.startswith('62'):
            digits = '0' + digits[2:]
        phone = extract_clean_phone(digits) or extract_clean_phone(el.text)
        if phone:
            break
    website = ""
    for el in driver.find_elements(By.CSS_SELECTOR, '[role="main"] a[data-item-id="authority"]'):
        website = el.get_attribute('href') or ""
        if website:
            break
    return phone, website


def qualifies_potential(lead):
    return any(re.fullmatch(r'[1-9][0-9]{7,14}', format_whatsapp_number(lead.get(field) or ''))
               is not None for field in ('phone', 'whatsapp'))


def scrape_gmap(keyword, location, limit=20, potential_only=False):
    limit = max(5, min(100, limit))
    candidate_limit = min(100, max(20, limit * 3)) if potential_only else limit
    deadline = time.monotonic() + 105
    qualified_count = 0
    query = f"{keyword} {location}".strip()
    url = f"https://www.google.com/maps/search/{query.replace(' ', '+')}?hl=id"

    options = Options()
    options.add_argument("--headless=new")
    options.add_argument("--no-sandbox")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--disable-gpu")
    options.add_argument("--disable-notifications")
    options.add_argument("--lang=id")
    options.add_argument("--window-size=1920,1080")
    options.add_argument("user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36")

    driver = None
    results = []
    seen_names = set()

    try:
        driver = create_chrome(options)
        driver.set_page_load_timeout(30)
        driver.get(url)
        time.sleep(3)

        # Accept Google consent if shown
        try:
            agree_buttons = driver.find_elements(By.XPATH, "//button[contains(., 'Setuju') or contains(., 'Accept all')]")
            if agree_buttons:
                agree_buttons[0].click()
                time.sleep(1)
        except Exception:
            pass

        # Try to locate the results container feed
        try:
            feed = WebDriverWait(driver, 8).until(
                EC.presence_of_element_located((By.XPATH, "//div[@role='feed']"))
            )
        except Exception:
            feed = None

        # Scroll feed to load more listings if needed
        scroll_iterations = min(8, max(2, (candidate_limit // 5) + 1))
        if feed:
            for _ in range(scroll_iterations):
                if time.monotonic() >= deadline:
                    break
                driver.execute_script("arguments[0].scrollTop = arguments[0].scrollTop + 1200;", feed)
                time.sleep(1.2)
                cards_count = len(driver.find_elements(By.XPATH, "//a[contains(@href, '/maps/place/')]"))
                if cards_count >= candidate_limit:
                    break

        # Extract listings
        cards = driver.find_elements(By.XPATH, "//a[contains(@href, '/maps/place/')]")

        for card in cards:
            if (time.monotonic() >= deadline or len(results) >= candidate_limit
                    or (potential_only and qualified_count >= limit)):
                break
            try:
                raw_name = card.get_attribute("aria-label") or ""
                name = clean_shop_name(raw_name)
                href = card.get_attribute("href") or ""

                if not name or name.lower() in seen_names or len(name) < 2:
                    continue
                seen_names.add(name.lower())

                # Locate card parent container
                parent = None
                try:
                    parent = card.find_element(By.XPATH, "./ancestor::div[contains(@class, 'Nv2PK')]")
                except Exception:
                    pass

                text_content = parent.text if parent else ""

                # Realtime Rating & Review count extraction with multi-strategy fallbacks
                rating = 0.0
                reviews_count = 0

                # Strategy 1: Check all aria-label attributes in parent card (e.g. '4,7 bintang 99 Ulasan')
                try:
                    aria_elements = parent.find_elements(By.XPATH, ".//*[@aria-label]")
                    for el in aria_elements:
                        al = el.get_attribute("aria-label") or ""
                        # Rating extraction from aria-label
                        if rating == 0.0 and ('bintang' in al.lower() or 'star' in al.lower()):
                            clean_al = al.replace(',', '.')
                            m_rate = re.search(r'([0-5]\.[0-9]|[0-5])\s*(?:bintang|stars|star)?', clean_al, re.IGNORECASE)
                            if m_rate:
                                rating = float(m_rate.group(1))

                        # Reviews count extraction from aria-label
                        if reviews_count == 0 and ('ulasan' in al.lower() or 'review' in al.lower()):
                            m_rev = re.search(r'([\d\.,]+)\s*(?:ulasan|reviews|review)', al, re.IGNORECASE)
                            if m_rev:
                                clean_digits = re.sub(r'\D', '', m_rev.group(1))
                                if clean_digits:
                                    reviews_count = int(clean_digits)
                except Exception:
                    pass

                # Strategy 2: Check Google Maps CSS classes (MW4etd for rating, UY7F9 for reviews)
                if rating == 0.0 and parent:
                    try:
                        mw_list = parent.find_elements(By.CSS_SELECTOR, "span.MW4etd")
                        if mw_list and mw_list[0].text:
                            txt_rate = mw_list[0].text.replace(',', '.').strip()
                            m_mw = re.search(r'([0-5]\.[0-9]|[0-5])', txt_rate)
                            if m_mw:
                                rating = float(m_mw.group(1))
                    except Exception:
                        pass

                if reviews_count == 0 and parent:
                    try:
                        uy_list = parent.find_elements(By.CSS_SELECTOR, "span.UY7F9")
                        if uy_list and uy_list[0].text:
                            clean_uy = re.sub(r'\D', '', uy_list[0].text)
                            if clean_uy:
                                reviews_count = int(clean_uy)
                    except Exception:
                        pass

                # Strategy 3: Check parentheses pattern in raw text content e.g. (99) or (1.234)
                if reviews_count == 0 and text_content:
                    try:
                        m_text_rev = re.search(r'\(([\d\.,]+)\)', text_content)
                        if m_text_rev:
                            clean_num = re.sub(r'\D', '', m_text_rev.group(1))
                            if clean_num:
                                reviews_count = int(clean_num)
                    except Exception:
                        pass

                # Parse lines from card text
                lines = [clean_unicode_icons(l).strip() for l in text_content.split('\n') if clean_unicode_icons(l).strip() and clean_unicode_icons(l).strip() != name]

                phone = ""
                raw_address = ""
                category = keyword
                website = ""

                # Look for website link in parent if available
                try:
                    web_elem = parent.find_elements(By.XPATH, ".//a[@data-value='Situs Web' or contains(@aria-label, 'Situs Web') or contains(@aria-label, 'Website')]")
                    if web_elem:
                        website = web_elem[0].get_attribute("href") or ""
                except Exception:
                    pass

                for line in lines:
                    extracted_p = extract_clean_phone(line)
                    if extracted_p and not phone:
                        phone = extracted_p

                    # Address detection
                    if ('Jl.' in line or 'Jalan' in line or 'No.' in line or 'Blok' in line or 'Ruko' in line or location.lower() in line.lower()) and not raw_address:
                        parts = [p.strip() for p in line.split('·') if p.strip()]
                        for part in parts:
                            if any(w in part for w in ['Jl.', 'Jalan', 'No.', 'Blok', 'Ruko', 'RT', 'RW', location]):
                                raw_address = part
                                break
                        if not raw_address and parts:
                            raw_address = parts[-1]

                    # Category detection
                    if not category or category == keyword:
                        if len(line) < 30 and not any(c.isdigit() for c in line) and 'bintang' not in line.lower() and 'ulasan' not in line.lower():
                            if '·' in line:
                                cat_part = line.split('·')[0].strip()
                                if cat_part and len(cat_part) < 30:
                                    category = cat_part

                # Preserve only explicit hours text exposed on this listing card.
                # This may be today's summary, not a complete weekly schedule.
                hours = []
                for line in lines:
                    if re.match(r'^(?:Buka|Tutup|Open|Closed|Opens|Closes)\b', line.strip(), re.IGNORECASE):
                        summary = re.split(r'\s+[·•]\s+(?=\+?\d[\d ()-]{6,})', line.strip())[0]
                        if re.search(r'\d{1,2}[:.]\d{2}|24\s*(?:jam|hours)|(?:pukul|at)\s+\d', summary, re.IGNORECASE):
                            hours.append(summary)
                opening_hours = '\n'.join(dict.fromkeys(hours)) or None
                address = clean_address(raw_address, location)
                whatsapp = format_whatsapp_number(phone)

                results.append({
                    "shop_name": name,
                    "category": category if category else keyword,
                    "address": address,
                    "phone": phone,
                    "whatsapp": whatsapp,
                    "rating": round(rating, 1) if rating > 0 else 0.0,
                    "reviews_count": reviews_count if reviews_count > 0 else 0,
                    "opening_hours": opening_hours,
                    "website": website,
                    "gmap_url": href
                })
                if potential_only and qualifies_potential(results[-1]):
                    qualified_count += 1
            except Exception:
                continue

        # Many categories (spa, hotel, restaurant) hide the phone on list cards; it only
        # appears in the place's detail panel. Navigating away makes the cards stale, so
        # this runs after every card has been read.
        for lead in results:
            if time.monotonic() >= deadline - 3 or (potential_only and qualified_count >= limit):
                break
            if lead["phone"] or not lead["gmap_url"]:
                continue
            try:
                phone, website = read_place_contact(driver, lead["gmap_url"], deadline)
            except Exception:
                continue
            if website and not lead["website"]:
                lead["website"] = website
            if phone:
                lead["phone"] = phone
                lead["whatsapp"] = format_whatsapp_number(phone)
                if potential_only:
                    qualified_count += 1

    except Exception as e:
        sys.stderr.write(f"Scraper error: {str(e)}\n")
        raise
    finally:
        if driver:
            try:
                driver.quit()
            except Exception:
                pass

    return results

if __name__ == "__main__":
    if hasattr(sys.stdout, 'reconfigure'):
        sys.stdout.reconfigure(encoding='utf-8')

    kw = sys.argv[1] if len(sys.argv) > 1 else "Laundry"
    loc = sys.argv[2] if len(sys.argv) > 2 else "Bekasi"
    lim = int(sys.argv[3]) if len(sys.argv) > 3 else 10

    try:
        data = scrape_hours(loc) if kw == '--hours' else scrape_gmap(kw, loc, lim, '--potential' in sys.argv[4:])
        print(json.dumps({"success": True, "items": data}, ensure_ascii=True))
    except Exception as error:
        sys.stderr.write(f'{type(error).__name__}: {error}\n')
        print(json.dumps({"success": False, "items": []}))
        sys.exit(1)
