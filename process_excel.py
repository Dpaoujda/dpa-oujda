import warnings
import pandas as pd
import mysql.connector
import numpy as np
import sys
import logging
import os
from datetime import datetime
import re

# Suppress warnings from openpyxl
warnings.filterwarnings("ignore", category=UserWarning, module="openpyxl")

# Set up logging to capture all issues with the Python script
logging.basicConfig(filename='process_excel_debug.log', level=logging.DEBUG, 
                    format='%(asctime)s - %(levelname)s - %(message)s')

def log_message(message, level='INFO'):
    """Helper function for logging messages with different severity levels."""
    if level == 'INFO':
        logging.info(message)
    elif level == 'ERROR':
        logging.error(message)
    elif level == 'WARNING':
        logging.warning(message)
    elif level == 'DEBUG':
        logging.debug(message)

def append_timestamp_to_filename(file_path):
    """Append a timestamp to the filename to avoid overwriting."""
    base, ext = os.path.splitext(file_path)
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    new_file_path = f"{base}_{timestamp}{ext}"
    return new_file_path

def validate_file(file_path):
    """Check if the file exists and has a valid extension."""
    if not os.path.isfile(file_path):
        log_message(f"❌ The file at {file_path} does not exist.", 'ERROR')
        return False
    if not file_path.endswith(('.xlsx', '.xls')):
        log_message(f"❌ Invalid file type. Expected an Excel file, got {file_path}.", 'ERROR')
        return False
    return True

def sanitize_table_name(sheet_name):
    """Sanitize sheet name to create valid MySQL table names."""
    return sheet_name.replace(" ", "_").replace("-", "_").replace("/", "_").replace(".", "_").lower()

def clean_column_names(columns):
    """Clean column names by replacing spaces with underscores, removing special characters, and converting to lowercase."""
    cleaned_columns = []
    for col in columns:
        # Replace spaces with underscores, remove non-alphanumeric characters (except underscores)
        cleaned_col = re.sub(r'[^a-zA-Z0-9_]', '', col.replace(' ', '_'))
        cleaned_columns.append(cleaned_col.lower())  # Convert to lowercase
    return cleaned_columns

def process_excel_file(file_path):
    try:
        # 📂 Retrieve the Excel file path from PHP script
        log_message(f"📂 File path received: {file_path}")

        # 🛢 Database configuration
        db_config = {
            'host': '127.0.0.1',
            'port': 3306,
            'user': 'root',
            'password': 'Mido',  # Update your MySQL password
            'database': 'agricole'  # Ensure 'agricole' database exists
        }

        # Validate file before proceeding
        if not validate_file(file_path):
            return

        # Append timestamp to avoid overwriting the file in the uploads folder
        new_file_path = append_timestamp_to_filename(file_path)
        log_message(f"📂 Saving file with timestamped name: {new_file_path}")
        os.rename(file_path, new_file_path)  # Renames the file to avoid overwriting

        log_message(f"📄 Loading Excel file: {new_file_path}")

        # 📄 Load the Excel file
        try:
            excel_file = pd.ExcelFile(new_file_path)
            log_message("✅ Excel file successfully loaded.")
        except Exception as e:
            log_message(f"❌ Error loading Excel file: {e}", 'ERROR')
            return

        # 🔌 Connect to MySQL
        try:
            conn = mysql.connector.connect(**db_config)
            cursor = conn.cursor()
            log_message("✅ Connected to MySQL")
        except mysql.connector.Error as err:
            log_message(f"❌ MySQL connection error: {err}", 'ERROR')
            return

        # 📝 Process each sheet in the Excel file
        for sheet_name in excel_file.sheet_names:
            log_message(f"\n📊 Processing sheet: {sheet_name}")

            df = pd.read_excel(new_file_path, sheet_name=sheet_name)

            # Check if dataframe is empty
            if df.empty:
                log_message(f"❌ The sheet '{sheet_name}' is empty. Skipping...", 'WARNING')
                continue

            # 🏷 Clean column names (skip `id` column if present)
            cleaned_columns = clean_column_names(df.columns)
            for i, col in enumerate(df.columns):
                if col.lower() == 'id':  # Preserve the `id` column as it is
                    cleaned_columns[i] = col
            df.columns = cleaned_columns
            log_message(f"✅ Cleaned column names: {df.columns.tolist()}")

            # 🔄 Replace NaN with empty strings
            df = df.fillna('')

            # 📌 Generate dynamic table name with sanitized sheet name
            table_name = sanitize_table_name(sheet_name)
            log_message(f"🛠 Sanitized table name: {table_name}")

            # 🔍 Detect column types for SQL
            column_types = ["`id` INT AUTO_INCREMENT PRIMARY KEY"]  # Add auto-increment primary key column

            for col in df.columns:
                if col.lower() == 'id':
                    continue  # Skip id column as it is already handled as the primary key
                sample_value = df[col].dropna().iloc[0] if not df[col].dropna().empty else ""
                if isinstance(sample_value, int):
                    column_types.append(f"`{col}` INT")
                elif isinstance(sample_value, float):
                    column_types.append(f"`{col}` FLOAT")
                elif isinstance(sample_value, str) and len(sample_value) < 255:
                    column_types.append(f"`{col}` VARCHAR(255)")
                else:
                    column_types.append(f"`{col}` TEXT")

            # 🛠 Drop existing table if it exists
            try:
                cursor.execute(f"DROP TABLE IF EXISTS `{table_name}`")
                log_message(f"🛠 Dropped existing table `{table_name}`.")
            except mysql.connector.Error as err:
                log_message(f"❌ Error dropping table `{table_name}`: {err}", 'ERROR')
                continue

            # 🛠 Create table with primary key `id`
            columns_definition = ", ".join(column_types)
            create_table_sql = f"CREATE TABLE `{table_name}` ({columns_definition});"
            log_message(f"🛠 Creating table `{table_name}`...")

            try:
                cursor.execute(create_table_sql)
                log_message(f"✅ Table `{table_name}` created.")
            except mysql.connector.Error as err:
                log_message(f"❌ Error creating table `{table_name}`: {err}", 'ERROR')
                continue  # Skip this sheet and move to the next one

            # ✅ Verify table creation
            cursor.execute(f"SHOW TABLES LIKE '{table_name}'")
            result = cursor.fetchone()
            if not result:
                log_message(f"❌ Table `{table_name}` was not created successfully!", 'ERROR')
                continue  # Skip this sheet if table creation failed

            # 🔄 Prepare insert statement
            placeholders = ", ".join(["%s"] * len(df.columns))
            columns = ", ".join([f"`{col}`" for col in df.columns])
            insert_sql = f"INSERT INTO `{table_name}` ({columns}) VALUES ({placeholders})"
            log_message(f"📌 Insert query: {insert_sql}")

            # OPTIONAL: Clear previous data before inserting new data
            try:
                cursor.execute(f"DELETE FROM `{table_name}` WHERE `id` IS NOT NULL")  # Do not delete `id` rows
                log_message(f"🗑 Cleared previous data from table `{table_name}` excluding `id` rows.")
            except mysql.connector.Error as err:
                log_message(f"❌ Error clearing data from `{table_name}`: {err}", 'ERROR')

            # 📥 Insert data into the table in batches (using executemany)
            rows = df.values.tolist()
            try:
                cursor.executemany(insert_sql, rows)
                log_message(f"✅ Data inserted into `{table_name}` successfully.")
            except mysql.connector.Error as err:
                log_message(f"❌ Error inserting data into `{table_name}`: {err}", 'ERROR')

            # Commit the data
            conn.commit()
            log_message(f"✅ Sheet '{sheet_name}' imported successfully.")

        cursor.close()
        conn.close()
        log_message("\n🎉 All sheets imported successfully!")

    except Exception as e:
        log_message(f"❌ Error: {e}", 'ERROR')

# Get the file path and process it
if __name__ == "__main__":
    file_path = sys.argv[1]  # Path passed by PHP
    process_excel_file(file_path)
