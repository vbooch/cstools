using System;
using System.Collections.Generic;
using NDesk.Options;
using System.IO;
using System.Linq;
using Newtonsoft.Json;
using Microsoft.CodeAnalysis.CSharp;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.Text;

namespace csast
{
    public class Program
    {
        public static void Main(string[] args)
        {
            var options = new OptionSet();

            List<string> files;
            try
            {
                files = options.Parse(args);
            }
            catch (OptionException e)
            {
                Console.Write("csast: ");
                Console.WriteLine(e.Message);
                Console.WriteLine("Try `csast --help' for more information.");
                return;
            }

            var results = new Dictionary<string, object>();

            foreach (var file in files)
            {
                using (var reader = new StreamReader(args[0]))
                {
                    var tree = CSharpSyntaxTree.ParseText(reader.ReadToEnd());

                    results.Add(file, ParseNodeOrToken(tree.GetRoot()));
                }

                var writer = new StreamWriter(Console.OpenStandardOutput());
                var jsonWriter = new JsonTextWriter(writer);
                var ser = new JsonSerializer();

                ser.Formatting = Newtonsoft.Json.Formatting.None;

                ser.Serialize(jsonWriter, results);

                jsonWriter.Flush();
            }
        }

        private static Dictionary<string, object> ParseNodeOrToken(SyntaxNodeOrToken o)
        {
            var node = o.IsNode ? o.AsNode() : null;
            var token = o.IsToken ? (SyntaxToken?)o.AsToken() : null;

            var dict = new Dictionary<string, object>();
            if (node != null)
            {
                dict["Type"] = "syntax";
                dict["ASTType"] = node.GetType().Name;
                dict["Text"] = node.GetText().ToString();
                dict["TrimmedText"] = node.GetText().ToString().Trim();
                dict["Span"] = GetAdjustedSpan(node.GetLocation().GetLineSpan().Span);
                dict["SpanStart"] = node.SpanStart;
                dict["Children"] = node.ChildNodesAndTokens().Select(ParseNodeOrToken).Cast<object>().ToList();
            }
            if (token != null)
            {
                dict["Type"] = "token";
                dict["ASTType"] = token.Value.GetType().Name;
                dict["Value"] = token.Value.Value;
                dict["Text"] = token.Value.ValueText;
                dict["TrimmedText"] = token.Value.ValueText.Trim();
                dict["Span"] = GetAdjustedSpan(token.Value.GetLocation().GetLineSpan().Span);
                dict["SpanStart"] = token.Value.SpanStart;
                dict["LeadingTrivia"] = ParseTrivia(token.Value.LeadingTrivia);
                dict["TrailingTrivia"] = ParseTrivia(token.Value.TrailingTrivia);
            }
            return dict;
        }

        private static List<object> ParseTrivia(IEnumerable<SyntaxTrivia> syntaxTriviaList)
        {
            var list = new List<object>();
            foreach (var trivia in syntaxTriviaList)
            {
                var dict = new Dictionary<string, object>();
                dict["Text"] = trivia.ToFullString();
                dict["Kind"] = trivia.Kind().ToString();
                dict["Span"] = GetAdjustedSpan(trivia.GetLocation().GetLineSpan().Span);
                dict["SpanStart"] = trivia.SpanStart;
                list.Add(dict);
            }
            return list;
        }

        private static LinePositionSpan GetAdjustedSpan(LinePositionSpan span)
        {
            return new LinePositionSpan(
                new LinePosition(span.Start.Line + 1, span.Start.Character + 1),
                new LinePosition(span.End.Line + 1, span.End.Character + 1)
            );
        }
    }
}

