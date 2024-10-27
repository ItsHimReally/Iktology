from langchain_community.document_loaders import DirectoryLoader, TextLoader
from langchain_huggingface import HuggingFaceEmbeddings
from langchain_community.embeddings import HuggingFaceBgeEmbeddings
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain_community.vectorstores import FAISS
from langchain_community.retrievers import BM25Retriever
from langchain.retrievers import EnsembleRetriever
from langchain_core.documents import Document

from transformers import AutoTokenizer
from vllm import LLM, SamplingParams
from tqdm.auto import tqdm


MODEL_NAME = "microsoft/Phi-3-mini-128k-instruct"
sessions = {}


loader = DirectoryLoader(
    'knowledgebase',
    glob="./*.md",
    loader_cls=TextLoader,
    show_progress=True,
    use_multithreading=True
)

documents = loader.load()

# Инициализируем эмбеддинг модель
embeddings = HuggingFaceBgeEmbeddings(
    model_name='TatonkaHF/bge-m3_en_ru',
    model_kwargs={"device": "cuda"}
)
# Загружаем векторную базу данных
rag_vectordb = FAISS.load_local(
    'db_index',  # from output folder
    embeddings,
    allow_dangerous_deserialization=True
)

def create_splits(documents, chunk_size=2000, chunk_overlap=100):
    """
    Разделяет текст на части (chunks) для поиска.

    :param documents: Список документов, которые нужно разделить.
    :param chunk_size: Размер каждого куска текста.
    :param chunk_overlap: Перекрытие между кусками текста.

    :return: Список кусков текста.
    """
    text_splitter = RecursiveCharacterTextSplitter(
        chunk_size=chunk_size,
        chunk_overlap=chunk_overlap
    )

    texts = text_splitter.split_documents(documents)

    return texts

def retrievers_init(text_db, vecotr_db, k=10):
    """
    Создает составной алгоритм поиска (Ensemble Retriever)
    сочетающий в себе BM25 и векторный поиск.

    :param text_db: Список текстов для поиска по ключевым словам.
    :param vecotr_db: Векторная база данных для поиска по смыслу.
    :param k: Количество результатов поиска.

    :return: Составной алгоритм поиска.
    """
    retriever_base = BM25Retriever.from_documents(text_db, k=k//2)
    retriever_advanced = vecotr_db.as_retriever(search_kwargs={"k": k//2, "search_type": "similarity"})
    ensemble_retriever = EnsembleRetriever(retrievers=[retriever_base, retriever_advanced], weights=[0.5, 0.5], k=k)

    return ensemble_retriever


rag_text_database = create_splits(documents)


sampling_params = SamplingParams(temperature=0.01, repetition_penalty=0.9, frequency_penalty=0.8, max_tokens=2048, min_tokens=10, top_k=1)
llm = LLM(model=MODEL_NAME, dtype='half', max_num_seqs=1, gpu_memory_utilization=0.8, max_model_len=25000)
tokenizer = AutoTokenizer.from_pretrained(MODEL_NAME)

rag_retriever = retrievers_init(rag_text_database, rag_vectordb)

def create_system_prompt(question, retriever):
    """
    Создает системный prompt для модели, включающий в себя контекст.
    :param question: Вопрос пользователя.
    :param retriever: Алгоритм поиска.

    :returns: Системный prompt для модели.
    """
    retriever_ans_raw = retriever.invoke(question, k=10)
    page_contents = [i.page_content for i in retriever_ans_raw]

    retriever_ans = '\n\n'.join(page_contents)

    system_prompt = f"""
    Твоя задача отвечать на вопросы пользователей связанные с экологией,
    тебе будет предоставлен контекст ответчай строго по нему.

    Контекст из которого необходимо взять информацию:
    {retriever_ans}

    ИНФОРМАЦИЮ ДЛЯ ОТВЕТА БЕРИ ТОЛЬКО ИЗ КОНТЕКСТА ВЫШЕ, ЕСЛИ ЕЕ ТАМ НЕТ НАПИШИ ИНФОРМАЦИЯ ОТСУТВУЕТ
    """

    return system_prompt


def get_model_ans(question, retriever=rag_retriever):
    """
    Получает ответ модели на заданный вопрос.

    :param question: Вопрос пользователя.
    :param retriever: Алгоритм поиска.

    :returns: Ответ модели.
    """
    system_prompt = create_system_prompt(question, retriever)

    prompt = tokenizer.apply_chat_template([{
        "role": "system",
        "content": system_prompt
    }, {
        "role": "user",
        "content": question,
    }], tokenize=False, add_generation_prompt=True)

    output = llm.generate(prompt, sampling_params)

    return output[0].outputs[0].text


def create_session(sID, text):
    """
    Создает сессию для пользователя с заданным текстом.

    :param sID: Идентификатор сессии.
    :param text: Текст пользователя.

    :return: None.
    """
    global sessions

    document = Document(
        page_content=text,
        metadata={"source": str(sID)}
    )

    user_texts = create_splits([document])
    user_vectordb = FAISS.from_documents(
        documents = user_texts,
        embedding = embeddings
    )

    user_retriever = retrievers_init(user_texts, user_vectordb)

    sessions[sID] = {
        'retriever': user_retriever,
        'texts_data': user_texts,
        'faiss': user_vectordb,
        'size_texts': len(text) 
    }


