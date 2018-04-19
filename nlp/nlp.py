'''
 # @app      				NLP
 # @author          Indyzen Inc
 # @added           2017.10.17
 # @framework 			Flask - Python

 # this is under active R&D development, please do not change

 # natural language processing api
 
 # https://[dev][qa]-chatbot.tapright.com/nlp/train

 	request [json format]:
	{
		"intent": "intents.json", // assuming already exists in 'saved_intents/' folder
		"model": "modelp.tflearn", // will be stored while training inside 'saved_models/' folder
		"training_data" : "training_data" // will be stored while training inside 'saved_models/' folder
	}
	response:
	{
		"message": "success",
		"status": true
	}

 # https://[dev][qa]-chatbot.tapright.com/nlp/get_intent

 	request [json format]:
	{
		"intent": "intents.json", // assuming already exists in 'saved_intents/' folder
		"model": "modelp.tflearn", // assuming already exists in 'saved_models/' folder
		"training_data" : "training_data", // assuming already exists in 'saved_models/' folder
		"user_content" : "which hours are you open?" // classify and predict the intent/tag based on trained model
	}
	response:
	{
		"intent": "hours",
		"status": true
	}
'''
import os
from flask import Flask
from flask import jsonify
from flask import request
import nltk
from nltk.stem.lancaster import LancasterStemmer
stemmer = LancasterStemmer()
import numpy as np
import tflearn
import tensorflow as tf
import random
import json
import pickle
import time
import datetime

app = Flask(__name__)

# to catch all url except train and classify routing
@app.route('/<path:dummy>', methods=['POST', 'GET'])
def fallback(dummy):
		rest_response = {
		'status': True,
		'message': 'TapRight Chatbot Natural Language Processing API'
		}
		return jsonify(rest_response)

# to train the model based on saved intents.json
@app.route('/train', methods=['POST'])
def train():
	os_file_path = os.path.join(os.path.dirname(__file__))
	if(len(os_file_path) != 0):
		os_file_path = os_file_path + '/'

	model_dir 	= os_file_path + 'saved_models/'
	intent_dir 	= os_file_path + 'saved_intents/'
	log_dir 		= os_file_path + 'saved_logs/'

	intent_name = request.json['intent'];
	model_name 	= request.json['model'];
	train_data_name = request.json['training_data'];

	with open( intent_dir + intent_name ) as json_data:
		intents = json.load(json_data)
		#print(intents)
	
	words = []
	classes = []
	documents = []
	ignore_words = ['?']
	# loop through each sentence in our intents patterns
	for intent in intents['intents']:
	    for pattern in intent['patterns']:
	        # tokenize each word in the sentence
	        w = nltk.word_tokenize(pattern)
	        # add to our words list
	        words.extend(w)
	        # add to documents in our corpus
	        documents.append((w, intent['tag']))
	        # add to our classes list
	        if intent['tag'] not in classes:
	            classes.append(intent['tag'])
	# stem and lower each word and remove duplicates
	words = [stemmer.stem(w.lower()) for w in words if w not in ignore_words]
	words = sorted(list(set(words)))
	#print(words)
	
	# remove duplicates
	classes = sorted(list(set(classes)))

	# create our training data
	training = []
	output = []
	# create an empty array for our output
	output_empty = [0] * len(classes)

	# training set, bag of words for each sentence
	for doc in documents:
	    # initialize our bag of words
	    bag = []
	    # list of tokenized words for the pattern
	    pattern_words = doc[0]
	    # stem each word
	    pattern_words = [stemmer.stem(word.lower()) for word in pattern_words]
	    # create our bag of words array
	    for w in words:
	        bag.append(1) if w in pattern_words else bag.append(0)

	    # output is a '0' for each tag and '1' for current tag
	    output_row = list(output_empty)
	    output_row[classes.index(doc[1])] = 1

	    training.append([bag, output_row])

	# shuffle our features and turn into np.array
	random.shuffle(training)
	training = np.array(training)

	# create train and test lists
	train_x = list(training[:,0])
	train_y = list(training[:,1])
	
	#calculate neuron count for the hidden layer
	neuron_count = int(np.sqrt(len(train_x[0]) * len(train_y[0])))
	# reset underlying graph data
	tf.reset_default_graph()
	# Build neural network
	net = tflearn.input_data(shape=[None, len(train_x[0])]) #input layer
	layer_1 = tflearn.fully_connected(net, neuron_count) #hidden layer 1
	#layer_2 = tflearn.fully_connected(layer_1, neuron_count) #hidden layer 2
	layer_3 = tflearn.fully_connected(layer_1, len(train_y[0]), activation='softmax') #output layer
	regressor_net = tflearn.regression(layer_3) #testing layer

	# Define model and setup tensorboard
	model = tflearn.DNN(regressor_net, tensorboard_dir = log_dir)
	# Start training (apply gradient descent algorithm)
	model.fit(train_x, train_y, n_epoch = 500, batch_size = neuron_count, show_metric = False)
	model.save( model_dir + model_name )

	# save all of our data structures
	import pickle
	pickle.dump( {'words':words, 'classes':classes, 'train_x':train_x, 'train_y':train_y}, open( model_dir + train_data_name, "wb" ) )

	rest_response = {
	'status': True,
	'message': 'success'
	}
	return jsonify(rest_response)

# to get intent classification for the user content based on saved intents.json and trained model
@app.route('/get_intent', methods=['POST'])
def classify():
	os_file_path = os.path.join(os.path.dirname(__file__))
	if(len(os_file_path) != 0):
		os_file_path = os_file_path + '/'

	model_dir 	= os_file_path + 'saved_models/'
	intent_dir 	= os_file_path + 'saved_intents/'
	log_dir 		= os_file_path + 'saved_logs/'

	intent_name = request.json['intent'];
	model_name 	= request.json['model'];
	train_data_name = request.json['training_data'];
	user_content = request.json['user_content'];

	data = pickle.load( open( model_dir + train_data_name, "rb" ) )
	words = data['words']
	classes = data['classes']
	train_x = data['train_x']
	train_y = data['train_y']

	# import our chat-bot intents file
	import json
	with open( intent_dir + intent_name ) as json_data:
		intents = json.load(json_data)

	#calculate neuron count for the hidden layer
	neuron_count = int(np.sqrt(len(train_x[0]) * len(train_y[0])))
	# reset underlying graph data
	tf.reset_default_graph()
	# build neural network
	net = tflearn.input_data(shape=[None, len(train_x[0])]) #input layer
	layer_1 = tflearn.fully_connected(net, neuron_count) #hidden layer 1
	#layer_2 = tflearn.fully_connected(layer_1, neuron_count) #hidden layer 2
	layer_3 = tflearn.fully_connected(layer_1, len(train_y[0]), activation='softmax') #output layer
	regressor_net = tflearn.regression(layer_3) #testing layer

	# Define model and setup tensorboard
	model = tflearn.DNN(regressor_net, tensorboard_dir = log_dir)

	def clean_up_sentence(sentence):
		# tokenize the pattern
		sentence_words = nltk.word_tokenize(sentence)
		# stem each word
		sentence_words = [stemmer.stem(word.lower()) for word in sentence_words]
		return sentence_words

	# return bag of words array: 0 or 1 for each word in the bag that exists in the sentence
	def bow(sentence, words):
	    # tokenize the pattern
	    sentence_words = clean_up_sentence(sentence)
	    # bag of words
	    bag = [0]*len(words)  
	    for s in sentence_words:
	        for i,w in enumerate(words):
	            if w == s: 
	                bag[i] = 1
	    return(np.array(bag))

	# load our saved model
	model.load( model_dir + model_name )

	# create a data structure to hold user context
	context = {}
	ERROR_THRESHOLD = 0.25
	def classify(sentence):
		# generate probabilities from the model
		results = model.predict([bow(sentence, words)])[0]
		
		# filter out predictions below a threshold
		results = [[i,r] for i,r in enumerate(results) if r>ERROR_THRESHOLD]
		# sort by strength of probability
		results.sort(key=lambda x: x[1], reverse=True)
		return_list = []

		# for r in results:
		# 	return_list.append((classes[r[0]], r[1]))
		
		#print(return_list)
		if len(results) == 0:
			return_list = 'default'
		else:
			return_list = classes[results[0][0]]

		# return tuple of intent and probability
		return return_list

	#find classification for the user content
	classification = classify(user_content)

	rest_response = {
	'status': True,
	'intent': classification
	}
	return jsonify(rest_response)

if __name__ == '__main__':
	app.run(debug=False, port=5050)
