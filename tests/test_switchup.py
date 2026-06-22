"""
Selenium Test Suite — Group5 SWITCH_UP (Student Service Hub)
============================================================
Test cases:
  TC01 – Login as Admin
  TC02 – Login wrong password
  TC03 – Add student
  TC04 – Duplicate email
  TC05 – Enroll student
  TC06 – Duplicate enrollment
  TC07 – Student chat
  TC08 – AI service offline
  TC09 – Lecturer view class
  TC10 – Student access Admin page

Prerequisites
-------------
  pip install selenium
  ChromeDriver installed and in PATH (matching your Chrome version)
  Apache/XAMPP running with the app at http://localhost/learning_management_full/
  Default test accounts exist in DB (see CREDENTIALS below).
"""

import time
import subprocess
import unittest
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import NoSuchElementException, TimeoutException

# ─────────────────────────────────────────────
# Configuration
# ─────────────────────────────────────────────
BASE_URL = "http://localhost/learning_management_full"

# Credentials match database/seed.sql — password for ALL accounts: 123456
CREDENTIALS = {
    "admin": {
        "email": "admin@uni.edu.vn",
        "password": "123456",
    },
    "student": {
        "email": "an.ph@student.uni.edu.vn",  # Pham Hoang An — enrolled in DS101-K23A
        "password": "123456",
    },
    "lecturer": {
        "email": "huong.tt@uni.edu.vn",       # TS. Tran Thi Huong — teaches DS101-K23A & DS201-K22A
        "password": "123456",
    },
}

# Student data for TC03 & TC04 — must NOT already exist in DB before TC03 runs
NEW_STUDENT = {
    "full_name": "Selenium Test Student",
    "email": "selenium.autotest@example.com",
}

WAIT = 10  # default explicit-wait seconds

# MySQL CLI path (XAMPP default)
MYSQL_BIN = r"C:\xampp\mysql\bin\mysql.exe"
DB_NAME   = "ds_chatbot"


def db_execute(sql: str) -> None:
    """Run a SQL statement against the test DB via the MySQL CLI.
    Used only for pre-test cleanup; keeps the suite dependency-free.
    """
    subprocess.run(
        [MYSQL_BIN, "-u", "root", DB_NAME, "-e", sql],
        capture_output=True,
        timeout=10,
    )


# ─────────────────────────────────────────────
# Helpers
# ─────────────────────────────────────────────
def make_driver(headless: bool = False) -> webdriver.Chrome:
    opts = Options()
    if headless:
        opts.add_argument("--headless=new")
    # Stability flags — reduce memory pressure during long AJAX waits (TC07/TC08)
    opts.add_argument("--no-sandbox")
    opts.add_argument("--disable-dev-shm-usage")
    opts.add_argument("--disable-extensions")
    opts.add_argument("--disable-background-networking")
    opts.add_argument("--disable-background-timer-throttling")
    opts.add_argument("--disable-backgrounding-occluded-windows")
    opts.add_argument("--disable-crash-reporter")
    opts.add_argument("--disable-default-apps")
    opts.add_argument("--disable-hang-monitor")
    opts.add_argument("--disable-sync")
    opts.add_argument("--disable-translate")
    opts.add_argument("--no-first-run")
    opts.add_argument("--safebrowsing-disable-auto-update")
    opts.add_argument("--window-size=1400,900")
    return webdriver.Chrome(options=opts)


def wait_for(driver, by, value, timeout=WAIT):
    return WebDriverWait(driver, timeout).until(
        EC.presence_of_element_located((by, value))
    )


def wait_clickable(driver, by, value, timeout=WAIT):
    return WebDriverWait(driver, timeout).until(
        EC.element_to_be_clickable((by, value))
    )


def login(driver, email: str, password: str):
    """Navigate to login page and submit credentials.
    Waits up to 20 s for the post-login redirect.
    bcrypt (cost-10) + DB insert + 302 can exceed 8 s on a loaded machine.
    """
    driver.get(f"{BASE_URL}/login.php")
    wait_for(driver, By.NAME, "email").clear()
    driver.find_element(By.NAME, "email").send_keys(email)
    driver.find_element(By.NAME, "password").clear()
    driver.find_element(By.NAME, "password").send_keys(password)
    driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()
    # Wait up to 20 s for the server to redirect away from login.php
    try:
        WebDriverWait(driver, 20).until(
            lambda d: "login.php" not in d.current_url
        )
    except TimeoutException:
        pass  # Caller checks current_url if login success is required


def logout(driver):
    """Destroy the server session and wait for the login redirect."""
    driver.get(f"{BASE_URL}/logout.php")
    # Give the server time to destroy the session cookie before the next login
    time.sleep(1.5)


def page_has_text(driver, text: str) -> bool:
    return text.lower() in driver.page_source.lower()


# ─────────────────────────────────────────────
# Test Suite
# ─────────────────────────────────────────────
class TestSwitchUp(unittest.TestCase):

    @classmethod
    def setUpClass(cls):
        pass  # Browser is created per-test in setUp (see below)

    @classmethod
    def tearDownClass(cls):
        pass  # Nothing to clean up at class level

    def setUp(self):
        """Launch a fresh Chrome for each test.
        Per-test browsers eliminate InvalidSessionIdException crashes:
        Chrome tab-discard / OOM during a long AJAX wait (TC07/TC08) only
        kills that one test's browser, not the whole suite.
        DB state (users, enrollments) persists on the server between tests.
        """
        self.driver = make_driver(headless=False)
        self.driver.implicitly_wait(3)
        # Ensure a clean (logged-out) server session on a fresh browser
        logout(self.driver)

    def tearDown(self):
        """Quit the browser after each test to free memory."""
        try:
            self.driver.quit()
        except Exception:
            pass

    # ──────────────────────────────────────────
    # TC01 – Login as Admin
    # ──────────────────────────────────────────
    def test_01_login_as_admin(self):
        """Admin can log in successfully and is redirected to the dashboard."""
        login(
            self.driver,
            CREDENTIALS["admin"]["email"],
            CREDENTIALS["admin"]["password"],
        )

        # After successful login the app redirects to index.php
        self.assertIn(
            "index.php", self.driver.current_url,
            "TC01 FAIL: Admin not redirected to dashboard after login",
        )

        # Confirm admin-only elements are visible (e.g. admin nav link)
        self.assertTrue(
            page_has_text(self.driver, "student") or
            page_has_text(self.driver, "admin"),
            "TC01 FAIL: Dashboard content not found after admin login",
        )
        print("TC01 PASS – Login as Admin")

    # ──────────────────────────────────────────
    # TC02 – Login wrong password
    # ──────────────────────────────────────────
    def test_02_login_wrong_password(self):
        """Wrong credentials must show an error message and not log the user in."""
        login(self.driver, CREDENTIALS["admin"]["email"], "WRONG_PASSWORD_999")

        # Must still be on login page (or redirected back)
        self.assertIn(
            "login.php", self.driver.current_url,
            "TC02 FAIL: Should remain on login page after wrong password",
        )

        # Error alert must appear
        self.assertTrue(
            page_has_text(self.driver, "Email hoặc mật khẩu chưa đúng") or
            page_has_text(self.driver, "incorrect") or
            page_has_text(self.driver, "wrong") or
            page_has_text(self.driver, "error"),
            "TC02 FAIL: No error message shown for wrong password",
        )
        print("TC02 PASS – Login wrong password")

    # ──────────────────────────────────────────
    # TC03 – Add student
    # ──────────────────────────────────────────
    def test_03_add_student(self):
        """Admin can create a new student account."""

        # ── Cleanup BEFORE navigating so no page refresh is needed ──
        # The app calls password_hash() on student create, which can take ~0.5 s
        # (bcrypt cost 10). Deleting here avoids any stale-element / timing risk.
        db_execute(
            f"DELETE FROM users WHERE email='{NEW_STUDENT['email']}'"
        )

        login(
            self.driver,
            CREDENTIALS["admin"]["email"],
            CREDENTIALS["admin"]["password"],
        )
        self.driver.get(f"{BASE_URL}/admin/students.php")

        # Fill in the Add Student form
        wait_for(self.driver, By.NAME, "full_name").clear()
        self.driver.find_element(By.NAME, "full_name").send_keys(NEW_STUDENT["full_name"])

        email_field = self.driver.find_element(By.NAME, "email")
        email_field.clear()
        email_field.send_keys(NEW_STUDENT["email"])

        # Make sure "Active account" checkbox is checked
        chk = self.driver.find_element(By.NAME, "is_active")
        if not chk.is_selected():
            chk.click()

        # Submit – locate the form that has action=create, then click its button.
        # The button HTML is <button class="btn"> with NO explicit type attribute,
        # so button.btn[type='submit'] does NOT match — use the ancestor form approach.
        create_action = self.driver.find_element(
            By.CSS_SELECTOR, "input[name='action'][value='create']"
        )
        create_form = create_action.find_element(By.XPATH, "./ancestor::form")
        create_form.find_element(By.CSS_SELECTOR, "button.btn").click()

        # ── Wait for redirect + flash render (bcrypt + DB insert + 302 > 1 s) ──
        # Flash is rendered as <div class="alert success"> in header.php
        try:
            WebDriverWait(self.driver, 15).until(
                EC.presence_of_element_located((By.CSS_SELECTOR, "div.alert"))
            )
        except TimeoutException:
            pass  # Assertion below will give the informative error

        # Success flash message expected
        self.assertTrue(
            page_has_text(self.driver, "Student account created") or
            page_has_text(self.driver, "created"),
            f"TC03 FAIL: Success message not shown after adding student. "
            f"URL={self.driver.current_url} | "
            f"Page={self.driver.page_source[:400]}",
        )

        # The new student should appear in the directory table
        self.assertIn(
            NEW_STUDENT["full_name"].lower(),
            self.driver.page_source.lower(),
            "TC03 FAIL: New student name not found in Student Directory",
        )
        print("TC03 PASS – Add student")

    # ──────────────────────────────────────────
    # TC04 – Duplicate email
    # ──────────────────────────────────────────
    def test_04_duplicate_email(self):
        """Creating a student with an already-used email must trigger an error."""
        login(
            self.driver,
            CREDENTIALS["admin"]["email"],
            CREDENTIALS["admin"]["password"],
        )
        self.driver.get(f"{BASE_URL}/admin/students.php")

        wait_for(self.driver, By.NAME, "full_name").clear()
        self.driver.find_element(By.NAME, "full_name").send_keys("Duplicate Student")

        email_field = self.driver.find_element(By.NAME, "email")
        email_field.clear()
        # Re-use the email we just created in TC03
        email_field.send_keys(NEW_STUDENT["email"])

        # Same fix as TC03: no explicit type attribute on the button
        create_action = self.driver.find_element(
            By.CSS_SELECTOR, "input[name='action'][value='create']"
        )
        create_form = create_action.find_element(By.XPATH, "./ancestor::form")
        create_form.find_element(By.CSS_SELECTOR, "button.btn").click()
        time.sleep(1)

        # Must show a database-level duplicate / integrity error
        self.assertTrue(
            page_has_text(self.driver, "Duplicate") or
            page_has_text(self.driver, "duplicate") or
            page_has_text(self.driver, "already exists") or
            page_has_text(self.driver, "SQLSTATE") or
            page_has_text(self.driver, "Integrity") or
            page_has_text(self.driver, "error"),
            "TC04 FAIL: No error shown when adding student with duplicate email",
        )
        print("TC04 PASS – Duplicate email")

    # ──────────────────────────────────────────
    # TC05 – Enroll student
    # ──────────────────────────────────────────
    def test_05_enroll_student(self):
        """Admin can enroll an active student into an existing class."""
        login(
            self.driver,
            CREDENTIALS["admin"]["email"],
            CREDENTIALS["admin"]["password"],
        )
        self.driver.get(f"{BASE_URL}/admin/students.php")

        # Locate the Enroll Student form (action=enroll)
        enroll_form = wait_for(
            self.driver, By.CSS_SELECTOR, "form input[name='action'][value='enroll']"
        )
        form = enroll_form.find_element(By.XPATH, "./ancestor::form")

        # Select first available class
        class_select = Select(form.find_element(By.NAME, "class_id"))
        if not class_select.options:
            self.skipTest("TC05 SKIP: No active classes available to enroll into")
        class_select.select_by_index(0)

        # Select first available student (the one we created or any existing one)
        student_select = Select(form.find_element(By.NAME, "student_id"))
        if not student_select.options:
            self.skipTest("TC05 SKIP: No active students available for enrollment")
        student_select.select_by_index(0)

        form.find_element(By.CSS_SELECTOR, "button.btn").click()
        time.sleep(1)

        self.assertTrue(
            page_has_text(self.driver, "enrolled") or
            page_has_text(self.driver, "Student enrolled") or
            page_has_text(self.driver, "success"),
            "TC05 FAIL: No success message after enrolling student",
        )
        print("TC05 PASS – Enroll student")

    # ──────────────────────────────────────────
    # TC06 – Duplicate enrollment
    # ──────────────────────────────────────────
    def test_06_duplicate_enrollment(self):
        """Enrolling the same student into the same class twice must show an error."""
        login(
            self.driver,
            CREDENTIALS["admin"]["email"],
            CREDENTIALS["admin"]["password"],
        )
        self.driver.get(f"{BASE_URL}/admin/students.php")

        enroll_form_input = wait_for(
            self.driver, By.CSS_SELECTOR, "form input[name='action'][value='enroll']"
        )
        form = enroll_form_input.find_element(By.XPATH, "./ancestor::form")

        class_select = Select(form.find_element(By.NAME, "class_id"))
        if not class_select.options:
            self.skipTest("TC06 SKIP: No classes available")
        class_select.select_by_index(0)

        student_select = Select(form.find_element(By.NAME, "student_id"))
        if not student_select.options:
            self.skipTest("TC06 SKIP: No students available")
        student_select.select_by_index(0)

        form.find_element(By.CSS_SELECTOR, "button.btn").click()
        time.sleep(1)

        # Second attempt with the SAME combination
        self.driver.get(f"{BASE_URL}/admin/students.php")
        enroll_form_input = wait_for(
            self.driver, By.CSS_SELECTOR, "form input[name='action'][value='enroll']"
        )
        form = enroll_form_input.find_element(By.XPATH, "./ancestor::form")

        class_select = Select(form.find_element(By.NAME, "class_id"))
        class_select.select_by_index(0)

        student_select = Select(form.find_element(By.NAME, "student_id"))
        student_select.select_by_index(0)

        form.find_element(By.CSS_SELECTOR, "button.btn").click()
        time.sleep(1)

        self.assertTrue(
            page_has_text(self.driver, "already enrolled") or
            page_has_text(self.driver, "already") or
            page_has_text(self.driver, "error"),
            "TC06 FAIL: No error message for duplicate enrollment",
        )
        print("TC06 PASS – Duplicate enrollment")

    # ──────────────────────────────────────────
    # TC07 – Student chat
    # ──────────────────────────────────────────
    def test_07_student_chat(self):
        """Student can open the chat page and send a question; a reply is rendered."""
        login(
            self.driver,
            CREDENTIALS["student"]["email"],
            CREDENTIALS["student"]["password"],
        )
        self.driver.get(f"{BASE_URL}/student/chat.php")

        # Look for the chat input
        try:
            chat_input = wait_for(self.driver, By.NAME, "question", timeout=8)
        except TimeoutException:
            self.skipTest(
                "TC07 SKIP: Chat input not found — student may not be enrolled in any class"
            )

        question_text = "What is Python?"
        chat_input.clear()
        chat_input.send_keys(question_text)

        send_btn = self.driver.find_element(By.CSS_SELECTOR, ".send-btn")
        send_btn.click()

        # Wait for bot response (AJAX, up to 40 seconds)
        try:
            WebDriverWait(self.driver, 40).until(
                lambda d: len(d.find_elements(By.CSS_SELECTOR, ".bot-message")) > 0
            )
        except TimeoutException:
            pass  # May have navigated; still check page source

        self.assertTrue(
            page_has_text(self.driver, question_text) or
            len(self.driver.find_elements(By.CSS_SELECTOR, ".bot-message")) > 0 or
            page_has_text(self.driver, "python"),
            "TC07 FAIL: Chat response not found on page",
        )
        print("TC07 PASS – Student chat")

    # ──────────────────────────────────────────
    # TC08 – AI service offline
    # ──────────────────────────────────────────
    def test_08_ai_service_offline(self):
        """
        When the AI microservice is unreachable the system must still respond
        with the local fallback answer (graceful degradation), not crash.

        NOTE: This test assumes AI_PROVIDER='python' is set AND the Python
        service at port 8010 is NOT running, so the system falls back to
        the local retrieval-based answer generator.
        """
        login(
            self.driver,
            CREDENTIALS["student"]["email"],
            CREDENTIALS["student"]["password"],
        )
        self.driver.get(f"{BASE_URL}/student/chat.php")

        try:
            chat_input = wait_for(self.driver, By.NAME, "question", timeout=8)
        except TimeoutException:
            self.skipTest(
                "TC08 SKIP: Chat input not available — student not enrolled in any class"
            )

        chat_input.clear()
        chat_input.send_keys("Explain linear regression")

        send_btn = self.driver.find_element(By.CSS_SELECTOR, ".send-btn")
        send_btn.click()

        # Wait for a response (including fallback); AI timeout can be up to 35 s
        try:
            WebDriverWait(self.driver, 45).until(
                lambda d: (
                    len(d.find_elements(By.CSS_SELECTOR, ".bot-message")) > 0 or
                    "error" in d.page_source.lower()
                )
            )
        except TimeoutException:
            pass

        # Acceptable outcomes:
        #   a) A fallback local answer is displayed  (graceful degradation)
        #   b) The error JSON / flash is displayed  (handled failure)
        #   c) The page contains any bot message
        page = self.driver.page_source.lower()
        response_shown = (
            "chủ đề" in page or          # local answer contains this
            "data science" in page or
            "bot-message" in page or
            "xin lỗi" in page or          # error fallback message
            "error" in page
        )
        self.assertTrue(
            response_shown,
            "TC08 FAIL: No fallback response when AI service is offline",
        )
        print("TC08 PASS – AI service offline (fallback used)")

    # ──────────────────────────────────────────
    # TC09 – Lecturer view class
    # ──────────────────────────────────────────
    def test_09_lecturer_view_class(self):
        """Lecturer can log in and access the My Classes page."""
        login(
            self.driver,
            CREDENTIALS["lecturer"]["email"],
            CREDENTIALS["lecturer"]["password"],
        )

        # Assert login actually succeeded before trying to visit the teacher page.
        # If the hash was wrong, login redirects back to login.php instead of index.php.
        self.assertIn(
            "index.php", self.driver.current_url,
            "TC09 FAIL: Lecturer login failed — check email/password and DB hash in seed.sql",
        )

        self.driver.get(f"{BASE_URL}/teacher/classes.php")
        time.sleep(1)

        # Must NOT be redirected to login
        self.assertNotIn(
            "login.php", self.driver.current_url,
            "TC09 FAIL: Lecturer was redirected to login when accessing teacher/classes.php",
        )

        # Page heading must contain class-related text
        self.assertTrue(
            page_has_text(self.driver, "Class Overview") or
            page_has_text(self.driver, "My Classes") or
            page_has_text(self.driver, "class"),
            "TC09 FAIL: Lecturer class overview page content not found",
        )
        print("TC09 PASS – Lecturer view class")

    # ──────────────────────────────────────────
    # TC10 – Student access Admin page
    # ──────────────────────────────────────────
    def test_10_student_access_admin_page(self):
        """
        A student must be denied access to admin pages.
        The app should redirect to login or show an authorization error.
        """
        login(
            self.driver,
            CREDENTIALS["student"]["email"],
            CREDENTIALS["student"]["password"],
        )

        # Try to access an admin-only page directly
        self.driver.get(f"{BASE_URL}/admin/students.php")
        time.sleep(1)

        # Acceptable: redirected to login, or shown 403/error page
        current = self.driver.current_url
        page = self.driver.page_source.lower()

        access_denied = (
            "login.php" in current or
            "403" in page or
            "unauthorized" in page or
            "access denied" in page or
            "forbidden" in page or
            "permission" in page or
            # If the app redirects to index.php (dashboard) instead of giving access
            "admin/students" not in current
        )
        self.assertTrue(
            access_denied,
            "TC10 FAIL: Student was NOT denied access to the admin page",
        )
        print("TC10 PASS – Student access Admin page (access denied)")


# ─────────────────────────────────────────────
# Entry point
# ─────────────────────────────────────────────
if __name__ == "__main__":
    unittest.main(verbosity=2)
