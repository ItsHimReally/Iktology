from typing import Any
import os

from fastapi import FastAPI, Request, Response, File, UploadFile, Query, Body
from pydantic import BaseModel
from fastapi.middleware.cors import CORSMiddleware
import uvicorn
import shutil
import requests
from dotenv import load_dotenv

import qa_system
from pdf_scanner import PDFScanner
import ya_gpt_prompts


load_dotenv()

app = FastAPI()

class Item(BaseModel):
    text: str

# Разрешить все источники, методы и заголовки
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],        # Разрешить все источники
    allow_credentials=True,
    allow_methods=["*"],        # Разрешить все методы (GET, POST и т.д.)
    allow_headers=["*"],        # Разрешить все заголовки
)

UPLOAD_FOLDER = "knowledgebase"
PDF_FOLDER = "pdfs"
Upload_Authorization = os.getenv("UPLOAD_AUTH")


@app.get("/api/")
async def read_root():
    """
    Возвращает приветственное сообщение.
    """
    return {"message": "Hello, FastAPI!"}


@app.get("/api/question")
async def get_question(request: Request):
    """
    Получение ответа на вопрос.

    :param request: Запрос
    :type request: Request

    :return: Ответ на вопрос
    """
    query = request.query_params.get('query')

    search_res = qa_system.get_model_ans(query)

    return {'reply': search_res}

@app.post("/api/summarize")
async def summary(text: str = Body(...)):
    """
    Кратко излагает суть текста докмунта

    :param text:  Текст документа
    :return:  Суммарный текст документа
    """
    summarize_res = ya_gpt_prompts.summarize(text)

    return {'reply': summarize_res}


@app.post("/api/doc_processing")
async def doc_processing(
        sID: str = Query(...),
        query: str = Query(...),
        text: str = Body(...)
    ):
    """
    Работа с одним документом. Помогает сотрудникам быстрее составить отчёт
    на основе документа

    :param sID: ид сессии сотрудники
    :param query: Вопрос сотрудника по документу
    :param text: Текст целевого документа
    :return: 
    """
    if sID not in qa_system.sessions:
        qa_system.create_session(sID, text)

    retriever = qa_system.sessions[sID]['retriever']

    search_res = qa_system.get_model_ans(query, retriever)

    return {'reply': search_res}


@app.get("/api/delete_session")
async def delete_session(request: Request):
    """
    Удаляет сессию пользователя по ID.

    :param request: Хедеры
    """
    
    user_sID = request.query_params.get('sID')

    if user_sID in qa_system.sessions:
        qa_system.sessions.pop(user_sID)

    return {'success': 'True'}


@app.post("/api/add_document")
async def add_doc(
    file: UploadFile = File(...), 
    doc_id: str = Query(...)
):
    """
    Добавляет документ в базу знаний.

    :param file: Файл
    :param doc_id: id файла
    """
    file_location = os.path.join(PDF_FOLDER, file.filename)

    with open(file_location, "wb") as f:
        content = await file.read()
        f.write(content)

    category, name, clean_text = PDFScanner.scan_full(file_location)

    md_location = os.path.join(UPLOAD_FOLDER, file.filename[:-3]+'md')
    with open(md_location, "w", encoding="utf-8") as file:
        file.write(clean_text)

    headers = {
        "Authorization": Upload_Authorization
    }
    url = "http://iktin.tw1.su/api/pushData/" 

    with open(md_location, "rb") as file:
        # Параметры запроса
        params = {"docID": doc_id}
        files = {"file": file}
        data = {
            "title": name,
            "category": category
        }

        response = requests.post(url, headers=headers, params=params, files=files, data=data)

    print(response)

    document = qa_system.Document(
        page_content=clean_text,
        metadata={"source": md_location}
    )

    new_data = qa_system.create_splits([document])

    qa_system.rag_text_database.extend(new_data)
    ids = qa_system.rag_vectordb.add_documents(documents=new_data)

    return {'success': 'True'}