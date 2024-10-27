import re
from ya_gpt_prompts import *
from pdfminer.high_level import extract_pages
from pdfminer.layout import LTTextContainer, LTChar, LTFigure
from PyPDF2 import PdfReader, PdfWriter
from pdf2image import convert_from_path
import pdfplumber
import pytesseract
from PIL import Image
from datetime import datetime, timedelta
import os
from pathlib import PurePosixPath

TESSERACT_PATH = 'C:/Program Files/Tesseract-OCR/tesseract.exe'
pytesseract.pytesseract.tesseract_cmd = TESSERACT_PATH

class PDFScanner: # Класс, отвечающий за сканирование PDF-Документов
    CATEGORY_UPPER_BOUND = 3000 # Кол-во символов, которые используются для определения категории
    NAME_UPPER_BOUND = 3000 # Кол-во символов, используемое для составления краткого имени документа  
    PATTERNS = { # Regex-паттерны для очистки артефактов в тексте
        r"[\d,]*\n|\n.{1,2}\n": r"\n",
        r"\n[\d,.=]{1,10}": r"\n",
        r"^\s*\S*[^|]\s*$": r"", 
        r"": "",
        r" *\n+": r"\n"
    }

    @staticmethod
    def scan_full(path) -> tuple[str, str, str]: # Полное сканирование PDF с получением категории, краткого названия, текста
        """
        Выполняет полное сканирование PDF-документа.

        :param path: Содержимое PDF-документа.
        :type path: str
        :return: Кортеж, содержащий категорию, краткое название и текст документа.
        :rtype: tuple[str, str, str]
        """
        pdf = PDFScanner.convert(path)
        text = PDFScanner.extract_text(pdf)
        category = categorize(text[0:PDFScanner.CATEGORY_UPPER_BOUND])
        delay = datetime.now() + timedelta(seconds=2)
        while datetime.now() < delay:
            pass
        name = rename(text[0:PDFScanner.NAME_UPPER_BOUND])
        return (category, name, text)

    
    @staticmethod
    def scan_partial(path) -> str: # Сканировать только текст
        """
        Извлекает текст из PDF-файла по указанному пути.

        :param path: Содержимое PDF-документа.
        :type path: str
        :return: Текст из PDF-файла.
        :rtype: str
        """
        pdf = PDFScanner.convert(path)
        text = PDFScanner.extract_text(pdf)
        return text
        
    @staticmethod
    def clean_text(text) -> str: # Очистка текста от артефактов
        """
        Очищает текст от артефактов, используя регулярные выражения из атрибута `PATTERNS`.

        :param text: Текст, который необходимо очистить.
        :type text: str
        :return: Очищенный текст.
        :rtype: str
        """
        for i in PDFScanner.PATTERNS:
            text = re.sub(i, PDFScanner.PATTERNS[i], text, flags=re.MULTILINE)
        return text
    
    @staticmethod
    def extract_text(path) -> str: # Извлечь текст
        """
        Извлекает текст из PDF-файла.

        :param path: Путь к PDF-файлу.
        :type path: str
        :return: Извлеченный текст.
        :rtype: str
        """
        pdf_read = PdfReader(path)
        pages_dict = {} 
        for pagenum, page in enumerate(extract_pages(path)): # Проходимся по каждой странице
            print("pagenum:", pagenum)
            page_obj = pdf_read.pages[pagenum]
            text_from_tables = []
            page_content = []
            table_in_page= -1
            pdf = pdfplumber.open(path)
            page_tables = pdf.pages[pagenum]

            tables = page_tables.find_tables() # Найти кол-во таблиц на странице
            if len(tables)!=0:
                table_in_page = 0

            # Извлечение таблиц
            for table_num in range(len(tables)):
                table = PDFScanner.extract_table(path, pagenum, table_num)
                table_string = PDFScanner.table_converter(table)
                text_from_tables.append(table_string)

            # Поиск элементов страницы
            page_elements = [(element.y1, element) for element in page._objs]
            # Сортировка в порядке их появления
            page_elements.sort(key=lambda a: a[0], reverse=True)


            scan_images = True # Сканировать картинки OCR'ом или нет
            for i, component in enumerate(page_elements): # Проходимся по каждому компоненту страницы
                element = component[1]

                # Смотрим кол-во таблиц на странице
                if table_in_page == -1:
                    pass
                else:
                    if PDFScanner.is_element_inside_any_table(element, page ,tables):
                        table_found = PDFScanner.find_table_for_element(element,page ,tables)
                        if table_found == table_in_page and table_found != None:    
                            table_in_page+=1
                        continue

                if not PDFScanner.is_element_inside_any_table(element,page,tables):
                    
                    # Если элемент - текстовое поле
                    if isinstance(element, LTTextContainer):
                        line_text = PDFScanner.extract_line(element)
                        page_content.append(line_text)
                        scan_images = False # Если увидели текст, то больше не сканируем картинки

                    # Считывание текста с изображений
                    if  scan_images and isinstance(element, LTFigure):
                        print("Scanning Image...")
                        PDFScanner.crop_image(element, page_obj)
                        PDFScanner.convert_to_images('cropped_image.pdf')
                        image_text = PDFScanner.image_to_text('PDF_image.png')
                        page_content.append(image_text)

            # Номер страницы          
            dctkey = pagenum
            # В словарь со страницами собираем текст каждой 
            pages_dict[dctkey]= [text_from_tables, page_content]

        md = ""
        for i in range(len(pages_dict)):
            for j in range(len(pages_dict[i][1])): # Добавление блоков текста
                md += PDFScanner.clean_text(pages_dict[i][1][j]) + '\n'
            for j in range(len(pages_dict[i][0])): # Добавление блоков таблиц
                md += pages_dict[i][0][j] + '\n'
        md = re.sub(r" *\n+", PDFScanner.PATTERNS[r" *\n+"], md, flags=re.MULTILINE)
        return md

    # Создать функцию для извлечения текста
    @staticmethod
    def extract_line(element):
        """
        Извлекает текст из элемента текстового поля.

        :param element: Элемент текстового поля.
        :type element: LTTextContainer
        :return: Текст из элемента.
        :rtype: str
        """
        line_text = element.get_text()
        return line_text

    @staticmethod
    def extract_table(pdf_path, page_num, table_num):
        """
        Извлекает таблицу из PDF-файла по указанному пути, номеру страницы и номеру таблицы.

        :param pdf_path: Путь к PDF-файлу.
        :type pdf_path: str
        :param page_num: Номер страницы.
        :type page_num: int
        :param table_num: Номер таблицы на странице.
        :type table_num: int
        :return: Таблица из PDF-файла.
        :rtype: list
        """
        pdf = pdfplumber.open(pdf_path)
        table_page = pdf.pages[page_num]
        table = table_page.extract_tables()[table_num]     
        return table

    # Преобразовать таблицу в соответствующий формат
    @staticmethod
    def table_converter(table):
        """
        Преобразует таблицу в строковый формат, разделенный символами "|" и "\n".

        :param table: Таблица из PDF-файла.
        :type table: list
        :return: Строковый формат таблицы.
        :rtype: str
        """
        table_string = ''
        # Перебрать каждую строку таблицы
        for row_num in range(len(table)):
            row = table[row_num]
            # Удалите разрыв строки из перенесенного текста.
            cleaned_row = [item.replace('\n', ' ') if item is not None and '\n' in item else '' if item is None else item for item in row]
            if row_num == 2:
                cleaned_row = ['-' for _ in row] 
                table_string+=('|'+'|'.join(cleaned_row)+'|'+'\n')
                continue
            if any(cleaned_row):
                table_string+=('|'+'|'.join(cleaned_row)+'|'+'\n')

        table_string = table_string[:-1]
        return table_string

    @staticmethod
    def is_element_inside_any_table(element, page, tables):
        """
        Проверяет, находится ли элемент внутри любой из таблиц на странице.

        :param element: Элемент PDF-страницы.
        :type element: LTPage
        :param page: Страница PDF-файла.
        :type page: LTPage
        :param tables: Список таблиц на странице.
        :type tables: list
        :return: True, если элемент находится в таблице, иначе False.
        :rtype: bool
        """
        x0, y0up, x1, y1up = element.bbox
        # Делаем так, чтобы pdfminer считал от нижнего до верхнего края страницы
        y0 = page.bbox[3] - y1up
        y1 = page.bbox[3] - y0up
        for table in tables:
            tx0, ty0, tx1, ty1 = table.bbox
            if tx0 <= x0 <= x1 <= tx1 and ty0 <= y0 <= y1 <= ty1:
                return True
        return False

    @staticmethod
    def find_table_for_element(element, page ,tables):
        """
        Находит таблицу, в которой находится элемент.

        :param element: Элемент PDF-страницы.
        :type element: LTPage
        :param page: Страница PDF-файла.
        :type page: LTPage
        :param tables: Список таблиц на странице.
        :type tables: list
        :return: Индекс таблицы, в которой находится элемент, или None, если элемент не находится в таблице.
        :rtype: int or None
        """
        x0, y0up, x1, y1up = element.bbox
        # Делаем так, чтобы pdfminer считал от нижнего до верхнего края страницы
        y0 = page.bbox[3] - y1up
        y1 = page.bbox[3] - y0up
        for i, table in enumerate(tables):
            tx0, ty0, tx1, ty1 = table.bbox
            if tx0 <= x0 <= x1 <= tx1 and ty0 <= y0 <= y1 <= ty1:
                return i  # Вернуть индекс таблицы
        return None  

    @staticmethod
    def convert(path):
        """
        Преобразует файл в PDF-формат, если он не является PDF-файлом.

        :param path: Путь к файлу.
        :type path: str
        :return: Путь к PDF-файлу.
        :rtype: str
        """
        if PurePosixPath(path).stem == "pdf":
            return path
        os.system(f"soffice --headless --convert-to pdf \"{path}\"")
        return PurePosixPath(path).stem + ".pdf"
    
    @staticmethod
    def crop_image(element, pageObj):
        """
        Обрезание изображения

        :param element: Элемент документа
        :param pageObj: Объект страницы
        :return: Обрезанное изображение
        """
        [image_left, image_top, image_right, image_bottom] = [element.x0,element.y0,element.x1,element.y1] 
        pageObj.mediabox.lower_left = (image_left, image_bottom)
        pageObj.mediabox.upper_right = (image_right, image_top)
        cropped_pdf_writer = PdfWriter()
        cropped_pdf_writer.add_page(pageObj)
        with open('cropped_image.pdf', 'wb') as cropped_pdf_file:
            cropped_pdf_writer.write(cropped_pdf_file)

    @staticmethod
    def convert_to_images(input_file,):
        """
        Преобразовать страницы в изображения

        :param input_file: PDF-файл
        """
        images = convert_from_path(input_file)
        image = images[0]
        output_file = 'PDF_image.png'
        image.save(output_file, 'PNG')

    @staticmethod
    def image_to_text(image_path):
        """
        Преобразует текст в изображение

        :param image_path: Путь к изображению
        :return: Текст с изображения
        """
        img = Image.open(image_path)
        text = pytesseract.image_to_string(img, lang='rus')
        return text

if __name__ == "__main__":
    path = "./Книга 1 Инвентаризация Био Агро Дон.pdf"
    with open("test.md", "w", encoding="utf-8") as file:
        file.write(PDFScanner.scan_partial(path))
