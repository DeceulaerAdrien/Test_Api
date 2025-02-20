from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from dotenv import load_dotenv
from mysql import connector
import json
import os

app = FastAPI()

# ===================== JSON Database =====================

JSON_DB = "db.json"

# Initialize JSON database
if not os.path.exists(JSON_DB):
    with open(JSON_DB, "w") as f:
        json.dump({"items": []}, f)

# Read from JSON
def read_json_db():
    with open(JSON_DB, "r") as f:
        return json.load(f)

# Write to JSON
def write_json_db(data):
    with open(JSON_DB, "w") as f:
        json.dump(data, f, indent=4)

@app.get("/json/items")
def get_json_items():
    data = read_json_db()
    return data["items"]

@app.post("/json/items")
def add_json_item(item: dict):
    data = read_json_db()
    data["items"].append(item)
    write_json_db(data)
    return item


# ===================== MySql Database =====================

load_dotenv()
class mysqlPipe(BaseModel):
    user: str = os.getenv("DB_USER")
    password: str = os.getenv("DB_PASSWORD")
    host: str = os.getenv("DB_HOST")
    database: str = os.getenv("DB_DATABASE")
    
    def connect(self):
        try:
            print("Connexion à MySQL...")
            c = connector.connect(
            host=self.host,
            user=self.user,
            password=self.password,
            database=self.database
            )
            print("Connexion réussie!")
            return c
            
        except connector.Error as e:
            print(f"Erreur de connexion à MySQL: {e}")
            raise e
        
mysqlPipe = mysqlPipe()

@app.get("/sql/items")
def get_sql_items():
    try:
        pipe = mysqlPipe.connect()
        c = pipe.cursor()
        c.execute("SELECT * FROM items")
        items = c.fetchall()

        print("Données récupérées:", items)
        pipe.close()
        return [{"id": row[0], "name": row[1], "price": row[2]} for row in items]
    
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur de connexion à la base de données: {e}")
    
@app.post("/sql/items")
def add_sql_item(name: str, value: int):
    pipe = mysqlPipe.connect()
    c = pipe.cursor()
    c.execute("INSERT INTO items (item_name, item_price) VALUES (%s, %s)", (name, value))
    pipe.commit()
    pipe.close()
    return {"name": name, "value": value}