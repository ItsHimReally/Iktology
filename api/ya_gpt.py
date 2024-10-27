import requests
import os
from dotenv import load_dotenv

# Ключи доступа
load_dotenv()
IAM_TOKEN = os.getenv("IAM_TOKEN") #  Получение токена из переменных окружения
FOLDER_ID = os.getenv("FOLDER_ID")  #  Получение ID папки из переменных окружения


class YA_GPT():
    """
    Класс для взаимодействия с YandexGPT.
    """
    URL = "https://llm.api.cloud.yandex.net/foundationModels/v1/completion" # API URL
    IAM_TOKEN = os.getenv("IAM_TOKEN")
    FOLDER_ID = os.getenv("FOLDER_ID")

    def __init__(self, system) -> None:
        """
        Инициализация класса.

        :param system: Преднастройка модели
        """
        self.system = system
        self.headers = {
            "Content-Type": "application/json",
            "Authorization": f"Api-Key {YA_GPT.IAM_TOKEN}",
        }

    def query(self, prompt):
        """
        Отправляет запрос к YandexGPT с заданным prompt и возвращает текстовый ответ.
        
        :param prompt:  Текст запроса к модели 
        :return:  Текстовый ответ модели
        """

        query = {
            "modelUri": f"gpt://{YA_GPT.FOLDER_ID}/yandexgpt-32k/rc",
            "completionOptions": {
                "stream": False,
                "temperature": 0.6,
                "maxTokens": "2000"
            },
            "messages": [
                {
                "role": "system",
                "text": self.system
                },
                {
                "role": "user",
                "text": prompt
                }
            ]
        }

        response = requests.post(YA_GPT.URL, headers=self.headers, json=query).json()
        return response["result"]["alternatives"][0]["message"]["text"]