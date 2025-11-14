"""
Veritabanı Yedekleme Management Command
"""
import os
import subprocess
import gzip
import shutil
from datetime import datetime
from pathlib import Path
from django.core.management.base import BaseCommand, CommandError
from django.conf import settings
from django.db import connection
from django_tenants.utils import get_tenant_model, get_public_schema_name
from apps.tenant_apps.backup.models import DatabaseBackup


class Command(BaseCommand):
    help = 'Veritabanını yedekler (public schema veya belirtilen tenant schema)'
    
    def add_arguments(self, parser):
        parser.add_argument(
            '--schema',
            type=str,
            help='Yedeklenecek schema adı (public veya tenant schema)',
            default=None
        )
        parser.add_argument(
            '--all',
            action='store_true',
            help='Tüm tenant schema\'larını yedekle',
        )
        parser.add_argument(
            '--type',
            type=str,
            choices=['manual', 'automatic'],
            default='manual',
            help='Yedekleme tipi',
        )
        parser.add_argument(
            '--user-id',
            type=int,
            help='Yedeklemeyi başlatan kullanıcı ID (opsiyonel)',
            default=None
        )
    
    def handle(self, *args, **options):
        schema_name = options.get('schema')
        backup_all = options.get('all', False)
        backup_type = options.get('type', 'manual')
        user_id = options.get('user_id')
        
        # Backup klasörünü oluştur
        backup_dir = self.get_backup_directory()
        backup_dir.mkdir(parents=True, exist_ok=True)
        
        # Güvenlik dosyalarını oluştur
        self.create_security_files(backup_dir)
        
        db_config = settings.DATABASES['default']
        
        # Güvenlik: Web request'ten çağrılıyorsa, sadece mevcut tenant'ın schema'sını yedekle
        from django.db import connection
        current_schema = connection.schema_name
        
        # Eğer --all parametresi yoksa ve schema belirtilmemişse, mevcut schema'yı kullan
        if not backup_all and not schema_name:
            # Web request'ten çağrılıyorsa (user_id varsa), güvenlik kontrolü yap
            if user_id and current_schema != get_public_schema_name():
                # Tenant context'inde çalışıyorsa, sadece mevcut tenant'ın schema'sını yedekle
                schema_name = current_schema
                self.stdout.write(
                    self.style.WARNING(
                        f'Güvenlik: Sadece mevcut tenant\'ın schema\'sı yedeklenecek: {schema_name}'
                    )
                )
            else:
                # Public schema'yı yedekle (sadece sistem/otomatik yedekleme için)
                schema_name = get_public_schema_name()
        elif schema_name and user_id:
            # Güvenlik: Web request'ten çağrılıyorsa, sadece mevcut tenant'ın schema'sını yedeklemeye izin ver
            if current_schema != get_public_schema_name() and schema_name != current_schema:
                raise CommandError(
                    f'Güvenlik hatası: Sadece kendi schema\'nızı yedekleyebilirsiniz. '
                    f'Mevcut schema: {current_schema}, İstenen schema: {schema_name}'
                )
        
        if backup_all:
            # Tüm tenant schema'larını yedekle (sadece otomatik yedekleme için, user_id yoksa)
            if user_id:
                raise CommandError(
                    'Güvenlik hatası: Web request\'ten tüm schema\'ları yedekleme izni yoktur. '
                    'Sadece otomatik yedekleme (--type=automatic) tüm schema\'ları yedekleyebilir.'
                )
            
            Tenant = get_tenant_model()
            tenants = Tenant.objects.exclude(schema_name=get_public_schema_name())
            
            self.stdout.write(f'Tüm tenant schema\'ları yedekleniyor ({tenants.count()} adet)...')
            
            for tenant in tenants:
                try:
                    self.backup_schema(
                        schema_name=tenant.schema_name,
                        db_config=db_config,
                        backup_dir=backup_dir,
                        backup_type=backup_type,
                        user_id=user_id
                    )
                except Exception as e:
                    self.stdout.write(
                        self.style.ERROR(f'Schema {tenant.schema_name} yedeklenirken hata: {str(e)}')
                    )
        elif schema_name:
            # Belirtilen schema'yı yedekle
            self.backup_schema(
                schema_name=schema_name,
                db_config=db_config,
                backup_dir=backup_dir,
                backup_type=backup_type,
                user_id=user_id
            )
        else:
            # Public schema'yı yedekle (sadece otomatik yedekleme için)
            self.backup_schema(
                schema_name=get_public_schema_name(),
                db_config=db_config,
                backup_dir=backup_dir,
                backup_type=backup_type,
                user_id=user_id
            )
    
    def get_backup_directory(self):
        """Backup klasörünü döndür"""
        base_dir = Path(settings.BASE_DIR)
        backup_dir = base_dir / 'backupdatabase'
        return backup_dir
    
    def create_security_files(self, backup_dir):
        """Güvenlik dosyalarını oluştur"""
        # .htaccess dosyası (Apache için)
        htaccess_path = backup_dir / '.htaccess'
        if not htaccess_path.exists():
            htaccess_content = """# Yedekleme klasörüne erişimi engelle
# Apache 2.2 ve 2.4 uyumlu
<IfModule mod_authz_core.c>
    # Apache 2.4+
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    # Apache 2.2
    Order Deny,Allow
    Deny from all
</IfModule>

# Dizin listeleme engelle
Options -Indexes

# Tüm dosya türlerine erişimi engelle
<FilesMatch ".*">
    Require all denied
</FilesMatch>

# PHP dosyalarını çalıştırma
<FilesMatch "\.php$">
    Require all denied
</FilesMatch>

# Python dosyalarını çalıştırma
<FilesMatch "\.py$">
    Require all denied
</FilesMatch>

# SQL dosyalarını engelle
<FilesMatch "\.sql$">
    Require all denied
</FilesMatch>

# Gzip dosyalarını engelle
<FilesMatch "\.gz$">
    Require all denied
</FilesMatch>
"""
            htaccess_path.write_text(htaccess_content, encoding='utf-8')
            self.stdout.write(self.style.SUCCESS(f'.htaccess dosyası oluşturuldu: {htaccess_path}'))
        
        # web.config dosyası (IIS için)
        webconfig_path = backup_dir / 'web.config'
        if not webconfig_path.exists():
            webconfig_content = """<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <authorization>
            <deny users="*" />
        </authorization>
        <directoryBrowse enabled="false" />
        <httpHandlers>
            <clear />
        </httpHandlers>
    </system.webServer>
</configuration>
"""
            webconfig_path.write_text(webconfig_content, encoding='utf-8')
            self.stdout.write(self.style.SUCCESS(f'web.config dosyası oluşturuldu: {webconfig_path}'))
        
        # index.html dosyası (boş sayfa)
        index_path = backup_dir / 'index.html'
        if not index_path.exists():
            index_content = """<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>403 Forbidden</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #d32f2f;
        }
    </style>
</head>
<body>
    <h1>403 Forbidden</h1>
    <p>Bu dizine erişim yasaktır.</p>
</body>
</html>
"""
            index_path.write_text(index_content, encoding='utf-8')
            self.stdout.write(self.style.SUCCESS(f'index.html dosyası oluşturuldu: {index_path}'))
        
        # .gitkeep dosyası (git için)
        gitkeep_path = backup_dir / '.gitkeep'
        if not gitkeep_path.exists():
            gitkeep_path.write_text('', encoding='utf-8')
        
        # .gitignore dosyası (yedek dosyalarını git'e ekleme)
        gitignore_path = backup_dir / '.gitignore'
        if not gitignore_path.exists():
            gitignore_content = """# Tüm yedek dosyalarını git'e ekleme
*.sql
*.sql.gz
*.gz
*.backup
*.bak

# Sadece güvenlik dosyalarını tut
!.htaccess
!web.config
!index.html
!.gitkeep
!.gitignore
"""
            gitignore_path.write_text(gitignore_content, encoding='utf-8')
            self.stdout.write(self.style.SUCCESS(f'.gitignore dosyası oluşturuldu: {gitignore_path}'))
    
    def backup_schema(self, schema_name, db_config, backup_dir, backup_type='manual', user_id=None):
        """Belirtilen schema'yı yedekle"""
        self.stdout.write(f'Schema yedekleniyor: {schema_name}')
        
        # Dosya adı oluştur (schema adından "tenant_" prefix'ini kaldır)
        schema_display_name = schema_name.replace('tenant_', '', 1) if schema_name.startswith('tenant_') else schema_name
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        file_name = f"backup_{schema_display_name}_{timestamp}.sql.gz"
        file_path = backup_dir / file_name
        
        # Backup kaydı oluştur
        backup_record = DatabaseBackup.objects.create(
            backup_type=backup_type,
            status='in_progress',
            file_name=file_name,
            file_path=str(file_path),
            schema_name=schema_name,
            database_name=db_config['NAME'],
            started_by_id=user_id,
        )
        
        try:
            # Önce pg_dump'ı kontrol et, yoksa Python alternatifini kullan
            pg_dump_path = self.find_pg_dump()
            
            if pg_dump_path:
                # pg_dump ile yedekleme (daha hızlı ve optimize)
                self.backup_with_pg_dump(schema_name, db_config, file_path, pg_dump_path)
            else:
                # Python ile yedekleme (psycopg2 kullanarak)
                self.backup_with_python(schema_name, db_config, file_path)
            
            # Dosya boyutunu al
            file_size = file_path.stat().st_size
            
            # Backup kaydını güncelle
            backup_record.status = 'completed'
            backup_record.file_size = file_size
            backup_record.completed_at = datetime.now()
            backup_record.save()
            
            self.stdout.write(
                self.style.SUCCESS(
                    f'Yedekleme tamamlandı: {file_name} ({self.format_size(file_size)})'
                )
            )
            
        except Exception as e:
            error_msg = str(e)
            backup_record.status = 'failed'
            backup_record.error_message = error_msg
            backup_record.completed_at = datetime.now()
            backup_record.save()
            raise CommandError(f'Yedekleme hatası: {error_msg}')
    
    def find_pg_dump(self):
        """pg_dump'ı bulmaya çalış"""
        # Önce PATH'te ara
        pg_dump_path = shutil.which('pg_dump')
        if pg_dump_path:
            return pg_dump_path
        
        # Windows'ta yaygın yolları kontrol et
        if os.name == 'nt':
            common_paths = [
                r'C:\Program Files\PostgreSQL\15\bin\pg_dump.exe',
                r'C:\Program Files\PostgreSQL\14\bin\pg_dump.exe',
                r'C:\Program Files\PostgreSQL\13\bin\pg_dump.exe',
                r'C:\Program Files\PostgreSQL\12\bin\pg_dump.exe',
                r'C:\Program Files (x86)\PostgreSQL\15\bin\pg_dump.exe',
                r'C:\Program Files (x86)\PostgreSQL\14\bin\pg_dump.exe',
                r'C:\Program Files (x86)\PostgreSQL\13\bin\pg_dump.exe',
                r'C:\Program Files (x86)\PostgreSQL\12\bin\pg_dump.exe',
            ]
            for path in common_paths:
                if os.path.exists(path):
                    return path
        
        return None
    
    def backup_with_pg_dump(self, schema_name, db_config, file_path, pg_dump_path):
        """pg_dump ile yedekleme (daha hızlı ve optimize)"""
        env = os.environ.copy()
        env['PGPASSWORD'] = db_config['PASSWORD']
        
        # pg_dump komutu
        cmd = [
            pg_dump_path,
            '-h', db_config['HOST'],
            '-p', str(db_config['PORT']),
            '-U', db_config['USER'],
            '-d', db_config['NAME'],
            '--schema', schema_name,
            '--no-owner',
            '--no-acl',
            '--format', 'plain',
        ]
        
        # pg_dump'ı çalıştır ve gzip ile sıkıştır
        self.stdout.write(f'pg_dump ile yedekleme başlatılıyor...')
        
        process = subprocess.Popen(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            env=env
        )
        
        # Çıktıyı gzip ile sıkıştır
        with gzip.open(file_path, 'wb') as f:
            for line in process.stdout:
                f.write(line)
        
        process.wait()
        
        if process.returncode != 0:
            error_output = process.stderr.read().decode('utf-8')
            raise CommandError(f'pg_dump hatası: {error_output}')
    
    def backup_with_python(self, schema_name, db_config, file_path):
        """Python ile yedekleme (psycopg2 kullanarak)"""
        try:
            import psycopg2
            from psycopg2.extensions import ISOLATION_LEVEL_AUTOCOMMIT
        except ImportError:
            raise CommandError(
                'psycopg2-binary yüklü değil. Lütfen şu komutu çalıştırın: '
                'pip install psycopg2-binary'
            )
        
        self.stdout.write(f'Python ile yedekleme başlatılıyor (psycopg2)...')
        
        # PostgreSQL bağlantısı kur
        conn = psycopg2.connect(
            host=db_config['HOST'],
            port=db_config['PORT'],
            user=db_config['USER'],
            password=db_config['PASSWORD'],
            database=db_config['NAME']
        )
        conn.set_isolation_level(ISOLATION_LEVEL_AUTOCOMMIT)
        
        try:
            cursor = conn.cursor()
            
            # SQL dump başlığı
            sql_header = f"""-- PostgreSQL database dump
-- Schema: {schema_name}
-- Dumped by: Python psycopg2
-- Dump date: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET row_security = off;

SET default_tablespace = '';
SET default_table_access_method = heap;

-- Schema: {schema_name}
CREATE SCHEMA IF NOT EXISTS {schema_name};
ALTER SCHEMA {schema_name} OWNER TO {db_config['USER']};

SET search_path TO {schema_name}, public;

"""
            
            # SQL içeriğini topla
            sql_content = [sql_header]
            
            # Tüm tabloları al
            cursor.execute("""
                SELECT tablename 
                FROM pg_tables 
                WHERE schemaname = %s
                ORDER BY tablename
            """, (schema_name,))
            
            tables = cursor.fetchall()
            
            for (table_name,) in tables:
                self.stdout.write(f'  Tablo yedekleniyor: {table_name}')
                
                # CREATE TABLE statement oluştur (pg_catalog kullanarak)
                # SQL injection koruması için quote_ident kullan
                cursor.execute("""
                    SELECT 
                        'CREATE TABLE ' || quote_ident(table_schema) || '.' || quote_ident(tablename) || ' (' ||
                        string_agg(
                            quote_ident(column_name) || ' ' || 
                            CASE 
                                WHEN data_type = 'character varying' THEN 'VARCHAR(' || COALESCE(character_maximum_length::text, '') || ')'
                                WHEN data_type = 'character' THEN 'CHAR(' || COALESCE(character_maximum_length::text, '') || ')'
                                WHEN data_type = 'numeric' THEN 'NUMERIC(' || COALESCE(numeric_precision::text, '') || ',' || COALESCE(numeric_scale::text, '0') || ')'
                                WHEN data_type = 'timestamp without time zone' THEN 'TIMESTAMP'
                                WHEN data_type = 'timestamp with time zone' THEN 'TIMESTAMPTZ'
                                WHEN data_type = 'time without time zone' THEN 'TIME'
                                WHEN data_type = 'time with time zone' THEN 'TIMETZ'
                                WHEN data_type = 'date' THEN 'DATE'
                                WHEN data_type = 'boolean' THEN 'BOOLEAN'
                                WHEN data_type = 'text' THEN 'TEXT'
                                WHEN data_type = 'bytea' THEN 'BYTEA'
                                WHEN data_type = 'json' THEN 'JSON'
                                WHEN data_type = 'jsonb' THEN 'JSONB'
                                WHEN data_type = 'uuid' THEN 'UUID'
                                ELSE UPPER(data_type)
                            END ||
                            CASE WHEN is_nullable = 'NO' THEN ' NOT NULL' ELSE '' END ||
                            CASE WHEN column_default IS NOT NULL THEN ' DEFAULT ' || column_default ELSE '' END,
                            ', '
                            ORDER BY ordinal_position
                        ) || ');'
                    FROM information_schema.columns
                    WHERE table_schema = %s AND table_name = %s
                    GROUP BY table_schema, tablename
                """, (schema_name, table_name))
                
                create_table_result = cursor.fetchone()
                if create_table_result and create_table_result[0]:
                    sql_content.append(f"\n-- Table: {table_name}\n")
                    sql_content.append(create_table_result[0])
                    sql_content.append("\n\n")
                
                # INSERT statements
                # SQL injection koruması için psycopg2.sql modülü kullan
                try:
                    from psycopg2 import sql
                    query = sql.SQL("SELECT * FROM {}.{}").format(
                        sql.Identifier(schema_name),
                        sql.Identifier(table_name)
                    )
                    cursor.execute(query)
                    
                    columns = [desc[0] for desc in cursor.description]
                    rows = cursor.fetchall()
                    
                    if rows:
                        sql_content.append(f"-- Data for table {table_name}\n")
                        for row in rows:
                            values = []
                            for val in row:
                                if val is None:
                                    values.append('NULL')
                                elif isinstance(val, str):
                                    # SQL injection koruması
                                    val = val.replace("'", "''").replace("\\", "\\\\")
                                    values.append(f"'{val}'")
                                elif isinstance(val, (int, float)):
                                    values.append(str(val))
                                elif isinstance(val, datetime):
                                    values.append(f"'{val.strftime('%Y-%m-%d %H:%M:%S')}'")
                                elif isinstance(val, bytes):
                                    # BYTEA için hex format
                                    values.append(f"'\\\\x{val.hex()}'")
                                else:
                                    val_str = str(val).replace("'", "''").replace("\\", "\\\\")
                                    values.append(f"'{val_str}'")
                            
                            # SQL injection koruması için quote_ident kullan
                            columns_str = ', '.join([f'"{col}"' for col in columns])
                            values_str = ', '.join(values)
                            # Schema ve table name'leri güvenli şekilde ekle
                            safe_schema = schema_name.replace('"', '""')
                            safe_table = table_name.replace('"', '""')
                            sql_content.append(f'INSERT INTO "{safe_schema}"."{safe_table}" ({columns_str}) VALUES ({values_str});\n')
                        sql_content.append("\n")
                except Exception as e:
                    # Tablo okunamazsa (örneğin view), sadece uyarı ver
                    self.stdout.write(self.style.WARNING(f'  Uyarı: {table_name} tablosu okunamadı: {str(e)}'))
                    sql_content.append(f"-- Warning: Could not read data from {table_name}\n\n")
            
            # SQL içeriğini gzip ile sıkıştır
            sql_text = ''.join(sql_content)
            with gzip.open(file_path, 'wb') as f:
                f.write(sql_text.encode('utf-8'))
            
            self.stdout.write(self.style.SUCCESS('Python ile yedekleme tamamlandı.'))
            
        finally:
            cursor.close()
            conn.close()
    
    def format_size(self, size_bytes):
        """Dosya boyutunu formatla"""
        for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
            if size_bytes < 1024.0:
                return f"{size_bytes:.2f} {unit}"
            size_bytes /= 1024.0
        return f"{size_bytes:.2f} PB"

