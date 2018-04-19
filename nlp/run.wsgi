import sys
import os
file_path = os.path.dirname(__file__)

#Expand Python classes path with your app's path
sys.path.insert(0, file_path)

from nlp import app

#Initialize WSGI app object
application = app
